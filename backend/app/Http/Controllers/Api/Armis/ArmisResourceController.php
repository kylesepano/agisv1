<?php

namespace App\Http\Controllers\Api\Armis;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArmisResourceProfileResource;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisWorkflowEvent;
use App\Models\User;
use App\Services\ArmisResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Exposes the scoped ARMIS-1A resource registry foundation API. */
class ArmisResourceController extends Controller
{
    public function __construct(private readonly ArmisResourceService $service) {}

    public function metadata(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => collect(ArmisResourceProfile::STATUSES)->map(fn (string $code): array => [
                    'code' => $code,
                    'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
                ])->values(),
                'categories' => collect(ArmisResourceProfile::CATEGORIES)->map(fn (string $code): array => [
                    'code' => $code,
                    'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
                ])->values(),
                'proficiencyLevels' => collect(['BASIC', 'INTERMEDIATE', 'ADVANCED', 'EXPERT'])
                    ->map(fn (string $code): array => ['code' => $code, 'label' => str($code)->lower()->headline()->toString()])
                    ->values(),
                'provider' => app(ResourcePlanningGateway::class)->status(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(ArmisResourceProfile::STATUSES)],
            'officeId' => ['nullable', 'integer', 'exists:offices,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'includeArchived' => ['nullable', 'boolean'],
        ]);
        $query = ArmisResourceProfile::query()
            ->with(['user', 'office'])
            ->withCount([
                'competencies' => fn ($query) => $query->where('is_current_revision', true),
                'availabilityPeriods',
            ]);
        $this->service->scopeVisible($query, $request->user());
        $query
            ->when($validated['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when(($validated['includeArchived'] ?? false) === true, fn ($q) => $q->withTrashed())
            ->when($validated['officeId'] ?? null, fn ($q, int $officeId) => $q->where('office_id', $officeId))
            ->when($validated['search'] ?? null, function ($q, string $search): void {
                $term = '%'.mb_strtolower($search).'%';
                $q->where(function ($nested) use ($term): void {
                    $nested->whereRaw('LOWER(resource_code) LIKE ?', [$term])
                        ->orWhereHas('user', fn ($user) => $user->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(employee_id) LIKE ?', [$term]))
                        ->orWhereHas('office', fn ($office) => $office->whereRaw('LOWER(name) LIKE ?', [$term])->orWhereRaw('LOWER(code) LIKE ?', [$term]));
                });
            });

        $profiles = $query->orderBy('resource_code')->get();

        return response()->json([
            'success' => true,
            'data' => ArmisResourceProfileResource::collection($profiles)->resolve(),
            'meta' => [
                'total' => $profiles->count(),
                'statuses' => $profiles->groupBy('status')->map->count(),
            ],
        ]);
    }

    public function identities(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('is_active', true)
            ->with('office:id,code,name')
            ->orderBy('name');
        if (! $request->user()->hasGlobalOfficeAccess()) {
            $query->where('office_id', $request->user()->office_id);
        }

        $users = $query->get(['id', 'employee_id', 'name', 'position', 'office_id']);

        return response()->json([
            'success' => true,
            'data' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'employeeId' => $user->employee_id,
                'name' => $user->name,
                'position' => $user->position,
                'officeId' => $user->office_id,
                'office' => $user->office ? [
                    'id' => $user->office->id,
                    'code' => $user->office->code,
                    'name' => $user->office->name,
                ] : null,
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $profile = $this->service->create($request, $this->validatedProfile($request));

        return (new ArmisResourceProfileResource($profile))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ArmisResourceProfile $profile): ArmisResourceProfileResource
    {
        $this->service->resolveVisible($request->user(), $profile->id);
        $profile->load([
            'user', 'office',
            'competencies' => fn ($query) => $query->where('is_current_revision', true)->with('competency'),
            'availabilityPeriods' => fn ($query) => $query->orderBy('start_date'),
        ]);

        return new ArmisResourceProfileResource($profile);
    }

    public function events(Request $request, ArmisResourceProfile $profile): JsonResponse
    {
        $this->service->resolveVisible($request->user(), $profile->id);
        $events = ArmisWorkflowEvent::query()
            ->where('subject_type', ArmisResourceProfile::class)
            ->where('subject_id', $profile->id)
            ->with('actor:id,name,employee_id')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ArmisWorkflowEvent $event): array => [
                'id' => $event->id,
                'eventCode' => $event->event_code,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'reason' => $event->reason,
                'metadata' => $event->metadata,
                'actor' => $event->actor ? [
                    'id' => $event->actor->id,
                    'name' => $event->actor->name,
                    'employeeId' => $event->actor->employee_id,
                ] : null,
                'createdAt' => $event->created_at?->toISOString(),
            ]);

        return response()->json(['success' => true, 'data' => $events]);
    }

    public function update(Request $request, ArmisResourceProfile $profile): ArmisResourceProfileResource
    {
        return new ArmisResourceProfileResource(
            $this->service->update($request, $profile, $this->validatedProfile($request, true)),
        );
    }

    public function transition(Request $request, ArmisResourceProfile $profile): ArmisResourceProfileResource
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(ArmisResourceProfile::STATUSES)],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:5000'],
        ]);
        $updated = $this->service->transition(
            $request,
            $profile,
            $validated['status'],
            (int) $validated['lockVersion'],
            $validated['reason'] ?? null,
        );

        return new ArmisResourceProfileResource($updated);
    }

    public function restore(Request $request, int $profile): ArmisResourceProfileResource
    {
        $resolved = $this->service->resolveVisible($request->user(), $profile, true);
        $validated = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);

        return new ArmisResourceProfileResource($this->service->restore($request, $resolved, (int) $validated['lockVersion']));
    }

    /** @return array<string, mixed> */
    private function validatedProfile(Request $request, bool $update = false): array
    {
        $routeProfile = $request->route('profile');
        $ignoreId = $routeProfile instanceof ArmisResourceProfile ? $routeProfile->id : null;
        $rules = [
            'resourceCode' => ['sometimes', 'string', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/i', Rule::unique('armis_resource_profiles', 'resource_code')->ignore($ignoreId)],
            'userId' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'officeId' => ['required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'category' => ['sometimes', 'string', Rule::in(ArmisResourceProfile::CATEGORIES)],
            'effectiveFrom' => ['nullable', 'date'],
            'effectiveTo' => ['nullable', 'date', 'after_or_equal:effectiveFrom'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
        if ($update) {
            $rules['lockVersion'] = ['required', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);

        return [
            'resource_code' => $validated['resourceCode'] ?? null,
            'user_id' => $validated['userId'],
            'office_id' => $validated['officeId'],
            'category' => $validated['category'] ?? null,
            'effective_from' => $validated['effectiveFrom'] ?? null,
            'effective_to' => $validated['effectiveTo'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'lock_version' => $validated['lockVersion'] ?? null,
        ];
    }
}
