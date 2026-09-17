<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Models\IapAuditorCapacity;
use App\Models\IapAuditorSkill;
use App\Models\IapAuditorUnavailability;
use App\Models\IapEngagementSkillRequirement;
use App\Models\IapPlanEngagement;
use App\Models\ArmisWorkloadAllocation;
use App\Models\InternalAuditPlan;
use App\Models\MasterListItem;
use App\Models\User;
use App\Services\IapPlanGuard;
use App\Services\IapScheduleConflictService;
use App\Services\IapSupport;
use App\Integrations\Aems\ArmisResourcePlanningGateway;
use App\Services\RuntimeConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Maintains interim auditor availability, skills, and annual person-day capacity.
 */
class IapResourceCapacityController extends Controller
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapScheduleConflictService $conflicts,
        private readonly IapSupport $support,
        private readonly RuntimeConfiguration $configuration,
        private readonly ArmisResourcePlanningGateway $resources,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscalYear' => ['nullable', 'integer', 'min:2000', 'max:2200'],
        ]);
        $visiblePlans = InternalAuditPlan::query();
        $this->guard->scopeVisible($visiblePlans, $request->user());
        $planIds = $visiblePlans->pluck('id');
        $years = InternalAuditPlan::query()
            ->whereIn('id', $planIds)
            ->distinct()
            ->orderByDesc('fiscal_year')
            ->pluck('fiscal_year')
            ->map(fn ($year) => (int) $year);
        $fiscalYear = (int) ($validated['fiscalYear'] ?? $years->first() ?? $this->configuration->currentFiscalYear());
        $auditors = $this->auditors();

        $allocated = $this->allocatedPersonDays($fiscalYear);
        $armisSkills = $this->resources->skills($auditors->pluck('id')->map(fn ($id): int => (int) $id)->all());
        $auditorPayload = $auditors->map(function (User $user) use (
            $allocated,
            $fiscalYear,
            $armisSkills,
        ): array {
            $available = $this->conflicts->capacityFor($fiscalYear, $user->id);
            $used = (float) ($allocated[$user->id] ?? 0);
            $periods = $this->resources->unavailability(
                $user->id,
                now()->startOfYear(),
                now()->endOfYear(),
            );
            $todayUnavailable = collect($periods)->contains(fn (array $period): bool =>
                $period['startDate'] <= today()->toDateString()
                && $period['endDate'] >= today()->toDateString());

            return [
                'id' => $user->id,
                'employeeId' => $user->employee_id,
                'name' => $user->name,
                'initials' => $user->initials,
                'position' => $user->position,
                'roleCode' => $user->role?->code,
                'availableToday' => ! $todayUnavailable,
                'availablePersonDays' => $available,
                'allocatedPersonDays' => $used,
                'remainingPersonDays' => round($available - $used, 2),
                'utilizationPercentage' => $available > 0
                    ? round(($used / $available) * 100, 1)
                    : ($used > 0 ? 100 : 0),
                'isOverallocated' => $used > $available,
                'skills' => collect($armisSkills[$user->id] ?? [])->values(),
                'unavailability' => collect($periods)->values(),
            ];
        })->values();

        $engagements = IapPlanEngagement::query()
            ->whereIn('plan_id', $planIds)
            ->whereHas('plan', fn ($plan) => $plan->where('fiscal_year', $fiscalYear))
            ->with([
                'plan:id,plan_code,fiscal_year,status',
                'offices:id,code,name',
                'auditAreas:id,code,name',
                'teamMembers.user:id,employee_id,name,initials',
                'skillRequirements.specialization',
            ])
            ->orderBy('engagement_code')
            ->get()
            ->map(fn ($engagement) => $this->engagementPayload($engagement))
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'fiscalYear' => $fiscalYear,
                'authoritativeProvider' => 'ARMIS',
                'legacyResourceWritesDisabled' => true,
                'years' => $years->contains($fiscalYear)
                    ? $years->values()
                    : $years->prepend($fiscalYear)->values(),
                'auditors' => $auditorPayload,
                'engagements' => $engagements,
                'specializations' => $this->masterOptions('IAP_AUDITOR_SPECIALIZATION'),
                'unavailabilityTypes' => $this->masterOptions('IAP_UNAVAILABILITY_TYPE'),
                'proficiencyLevels' => [
                    ['code' => 'BASIC', 'label' => 'Basic'],
                    ['code' => 'INTERMEDIATE', 'label' => 'Intermediate'],
                    ['code' => 'ADVANCED', 'label' => 'Advanced'],
                    ['code' => 'EXPERT', 'label' => 'Expert'],
                ],
                'summary' => [
                    'availableAuditors' => $auditorPayload
                        ->where('availableToday', true)->count(),
                    'totalAuditors' => $auditorPayload->count(),
                    'availablePersonDays' => round(
                        (float) $auditorPayload->sum('availablePersonDays'),
                        2,
                    ),
                    'allocatedPersonDays' => round(
                        (float) $auditorPayload->sum('allocatedPersonDays'),
                        2,
                    ),
                    'requiredPersonDays' => round(
                        (float) $engagements
                            ->whereNotIn('scheduleStatus', ['CANCELLED'])
                            ->sum('requiredPersonDays'),
                        2,
                    ),
                    'overallocatedAuditors' => $auditorPayload
                        ->where('isOverallocated', true)->count(),
                    'engagementsWithSkillGaps' => $engagements
                        ->filter(fn ($engagement) => $engagement['skillGaps'] !== [])
                        ->count(),
                ],
            ],
        ]);
    }

    public function updateCapacity(Request $request, User $user): JsonResponse
    {
        $this->assertAuditor($request, $user);
        return $this->armisAuthoritativeResponse();
        /* $validated = $request->validate([
            'fiscalYear' => ['required', 'integer', 'min:2000', 'max:2200'],
            'availablePersonDays' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $capacity = IapAuditorCapacity::query()->updateOrCreate(
            ['fiscal_year' => $validated['fiscalYear'], 'user_id' => $user->id],
            [
                'available_person_days' => $validated['availablePersonDays'],
                'notes' => $validated['notes'] ?? null,
                'set_by' => $request->user()->id,
            ],
        );
        $this->support->audit(
            $request,
            'iap.capacity.updated',
            $capacity,
            null,
            $capacity->toArray(),
        );

        return $this->success('Auditor annual capacity updated.'); */
    }

    public function storeUnavailability(Request $request, User $user): JsonResponse
    {
        $this->assertAuditor($request, $user);
        return $this->armisAuthoritativeResponse();
        /* $validated = $this->validateUnavailability($request);
        $period = IapAuditorUnavailability::query()->create([
            ...$this->unavailabilityAttributes($validated),
            'user_id' => $user->id,
            'created_by' => $request->user()->id,
        ]);
        $this->support->audit(
            $request,
            'iap.availability.unavailable_created',
            $period,
            null,
            $period->toArray(),
        );

        return $this->success('Unavailable period added.', 201); */
    }

    public function updateUnavailability(
        Request $request,
        IapAuditorUnavailability $unavailability,
    ): JsonResponse {
        $this->guard->assertManagement($request->user());
        return $this->armisAuthoritativeResponse();
        /* $validated = $this->validateUnavailability($request);
        $before = $unavailability->toArray();
        $unavailability->update($this->unavailabilityAttributes($validated));
        $this->support->audit(
            $request,
            'iap.availability.unavailable_updated',
            $unavailability,
            $before,
            $unavailability->fresh()->toArray(),
        );

        return $this->success('Unavailable period updated.'); */
    }

    public function destroyUnavailability(
        Request $request,
        IapAuditorUnavailability $unavailability,
    ): JsonResponse {
        $this->guard->assertManagement($request->user());
        return $this->armisAuthoritativeResponse();
        /* $unavailability->delete();
        $this->support->audit(
            $request,
            'iap.availability.unavailable_archived',
            $unavailability,
            ['deletedAt' => null],
            ['deletedAt' => $unavailability->deleted_at?->toISOString()],
        );

        return $this->success('Unavailable period archived.'); */
    }

    public function restoreUnavailability(Request $request, int $unavailability): JsonResponse
    {
        $this->guard->assertManagement($request->user());
        return $this->armisAuthoritativeResponse();
        /* $period = IapAuditorUnavailability::withTrashed()->findOrFail($unavailability);
        $period->restore();
        $this->support->audit(
            $request,
            'iap.availability.unavailable_restored',
            $period,
            ['deletedAt' => 'archived'],
            ['deletedAt' => null],
        );

        return $this->success('Unavailable period restored.'); */
    }

    public function syncSkills(Request $request, User $user): JsonResponse
    {
        $this->assertAuditor($request, $user);
        return $this->armisAuthoritativeResponse();
        /* $validated = $request->validate([
            'skills' => ['present', 'array', 'max:50'],
            'skills.*.specializationId' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('master_list_items', 'id')->whereNull('deleted_at'),
            ],
            'skills.*.proficiencyLevel' => [
                'required',
                Rule::in(['BASIC', 'INTERMEDIATE', 'ADVANCED', 'EXPERT']),
            ],
            'skills.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->assertMasterItems(
            collect($validated['skills'])->pluck('specializationId'),
            'IAP_AUDITOR_SPECIALIZATION',
        );
        DB::transaction(function () use ($request, $user, $validated): void {
            $user->iapSkills()->delete();
            foreach ($validated['skills'] as $skill) {
                IapAuditorSkill::query()->create([
                    'user_id' => $user->id,
                    'specialization_id' => $skill['specializationId'],
                    'proficiency_level' => $skill['proficiencyLevel'],
                    'notes' => $skill['notes'] ?? null,
                    'verified_by' => $request->user()->id,
                    'verified_at' => now(),
                ]);
            }
        }, 3);
        $this->support->audit(
            $request,
            'iap.auditor_skills.updated',
            $user,
            null,
            ['skills' => $validated['skills']],
        );

        return $this->success('Auditor specializations updated.'); */
    }

    public function syncRequirements(
        Request $request,
        IapPlanEngagement $engagement,
    ): JsonResponse {
        $this->guard->assertManagement($request->user());
        return $this->armisAuthoritativeResponse();
        /* $this->guard->assertEditable($request->user(), $engagement->plan);
        $validated = $request->validate([
            'requirements' => ['present', 'array', 'max:50'],
            'requirements.*.specializationId' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('master_list_items', 'id')->whereNull('deleted_at'),
            ],
            'requirements.*.minimumAuditors' => ['required', 'integer', 'min:1', 'max:99'],
            'requirements.*.minimumProficiency' => [
                'required',
                Rule::in(['BASIC', 'INTERMEDIATE', 'ADVANCED', 'EXPERT']),
            ],
            'requirements.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->assertMasterItems(
            collect($validated['requirements'])->pluck('specializationId'),
            'IAP_AUDITOR_SPECIALIZATION',
        );
        DB::transaction(function () use ($engagement, $validated): void {
            $engagement->skillRequirements()->delete();
            foreach ($validated['requirements'] as $requirement) {
                IapEngagementSkillRequirement::query()->create([
                    'plan_engagement_id' => $engagement->id,
                    'specialization_id' => $requirement['specializationId'],
                    'minimum_auditors' => $requirement['minimumAuditors'],
                    'minimum_proficiency' => $requirement['minimumProficiency'],
                    'notes' => $requirement['notes'] ?? null,
                ]);
            }
        }, 3);
        $this->support->audit(
            $request,
            'iap.engagement_skill_requirements.updated',
            $engagement,
            null,
            ['requirements' => $validated['requirements']],
        );

        return $this->success('Engagement skill requirements updated.'); */
    }

    private function auditors(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.assign_team'))
            ->with('role:id,code,name')
            ->orderBy('name')
            ->get([
                'id',
                'employee_id',
                'name',
                'initials',
                'position',
                'role_id',
            ]);
    }

    private function allocatedPersonDays(int $fiscalYear): Collection
    {
        return ArmisWorkloadAllocation::query()
            ->whereHas('resourceProfile', fn ($query) => $query->where('status', 'ACTIVE'))
            ->where('armis_workload_allocations.fiscal_year', $fiscalYear)
            ->where('armis_workload_allocations.source_module', 'IAP')
            ->where('armis_workload_allocations.source_type', 'IAP_PLAN_ENGAGEMENT')
            ->where('armis_workload_allocations.status', '!=', 'RETURNED')
            ->where('armis_workload_allocations.is_current_revision', true)
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('iap_plan_engagements as source_engagement')
                    ->whereColumn('source_engagement.id', 'armis_workload_allocations.source_id')
                    ->where('source_engagement.schedule_status', 'SCHEDULED')
                    ->whereNull('source_engagement.deleted_at');
            })
            ->groupBy('armis_resource_profiles.user_id')
            ->selectRaw('armis_resource_profiles.user_id, SUM(armis_workload_allocations.planned_person_days) as allocated')
            ->join('armis_resource_profiles', 'armis_resource_profiles.id', '=', 'armis_workload_allocations.resource_profile_id')
            ->pluck('allocated', 'user_id');
    }

    private function engagementPayload(IapPlanEngagement $engagement): array
    {
        $requirements = $engagement->skillRequirements->map(function ($requirement) use ($engagement): array {
            $qualified = $engagement->teamMembers->filter(
                fn ($member) => $this->userMeetsRequirement(
                    $member->user_id,
                    $requirement->specialization_id,
                    $requirement->minimum_proficiency,
                ),
            )->count();

            return [
                'id' => $requirement->id,
                'specializationId' => $requirement->specialization_id,
                'code' => $requirement->specialization?->code,
                'label' => $requirement->specialization?->label,
                'minimumAuditors' => $requirement->minimum_auditors,
                'minimumProficiency' => $requirement->minimum_proficiency,
                'qualifiedAuditors' => $qualified,
                'hasGap' => $qualified < $requirement->minimum_auditors,
                'notes' => $requirement->notes,
            ];
        })->values();

        return [
            'id' => $engagement->id,
            'engagementCode' => $engagement->engagement_code,
            'title' => $engagement->title,
            'planCode' => $engagement->plan?->plan_code,
            'planStatus' => $engagement->plan?->status,
            'scheduleStatus' => $engagement->schedule_status,
            'requiredPersonDays' => (float) $engagement->estimated_person_days,
            'assignedPersonDays' => (float) $engagement->teamMembers
                ->sum('planned_person_days'),
            'offices' => $engagement->offices
                ->map(fn ($office) => ['id' => $office->id, 'code' => $office->code, 'name' => $office->name])
                ->values(),
            'auditAreas' => $engagement->auditAreas
                ->map(fn ($area) => ['id' => $area->id, 'code' => $area->code, 'name' => $area->name])
                ->values(),
            'teamMembers' => $engagement->teamMembers
                ->map(fn ($member) => [
                    'userId' => $member->user_id,
                    'name' => $member->user?->name,
                    'plannedPersonDays' => (float) $member->planned_person_days,
                ])->values(),
            'requirements' => $requirements,
            'skillGaps' => $requirements->where('hasGap', true)->values(),
        ];
    }

    private function userMeetsRequirement(
        int $userId,
        int $specializationId,
        string $minimumProficiency,
    ): bool {
        $rank = ['BASIC' => 1, 'INTERMEDIATE' => 2, 'ADVANCED' => 3, 'EXPERT' => 4];
        $claims = $this->resources->skills([$userId], [$specializationId])[$userId] ?? [];

        return collect($claims)->contains(fn (array $claim): bool =>
            (int) ($claim['id'] ?? 0) === $specializationId
            && ($rank[$claim['proficiencyLevel'] ?? ''] ?? 0) >= ($rank[$minimumProficiency] ?? 0));
    }

    private function assertAuditor(Request $request, User $user): void
    {
        $this->guard->assertManagement($request->user());
        if (! $user->is_active || ! $user->hasPermission('iap.assign_team')) {
            throw ValidationException::withMessages([
                'user' => ['Resource records can only be maintained for an active CIAS auditor.'],
            ]);
        }
    }

    private function validateUnavailability(Request $request): array
    {
        $validated = $request->validate([
            'typeId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->assertMasterItems(
            collect([$validated['typeId']]),
            'IAP_UNAVAILABILITY_TYPE',
        );

        return $validated;
    }

    private function unavailabilityAttributes(array $validated): array
    {
        return [
            'unavailability_type_id' => $validated['typeId'],
            'title' => $validated['title'],
            'start_date' => $validated['startDate'],
            'end_date' => $validated['endDate'],
            'notes' => $validated['notes'] ?? null,
        ];
    }

    private function assertMasterItems(Collection $ids, string $listCode): void
    {
        $valid = MasterListItem::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereHas('masterList', fn ($list) => $list
                ->where('code', $listCode)
                ->where('is_active', true))
            ->count();
        if ($valid !== $ids->unique()->count()) {
            throw ValidationException::withMessages([
                'selection' => ["Every selection must be an active {$listCode} item."],
            ]);
        }
    }

    private function masterOptions(string $listCode): Collection
    {
        return MasterListItem::query()
            ->where('is_active', true)
            ->whereHas('masterList', fn ($list) => $list
                ->where('code', $listCode)
                ->where('is_active', true))
            ->orderBy('display_order')
            ->get(['id', 'code', 'label', 'description'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'code' => $item->code,
                'label' => $item->label,
                'description' => $item->description,
            ]);
    }

    private function success(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message], $status);
    }

    private function armisAuthoritativeResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'ARMIS is the authoritative resource workspace. Use /audit-resource-management/planning for capacity, availability, competencies, and workload changes.',
            'replacementPath' => '/audit-resource-management/planning',
            'sourceOfTruth' => 'ARMIS',
        ], 409);
    }
}
