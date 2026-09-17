<?php

namespace App\Http\Controllers\Api\Armis;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArmisCompetencyResource;
use App\Models\ArmisCompetency;
use App\Models\ArmisWorkflowEvent;
use App\Services\ArmisCompetencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Exposes the ARMIS-2A competency and certification review API. */
class ArmisCompetencyController extends Controller
{
    public function __construct(private readonly ArmisCompetencyService $service) {}

    public function metadata(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->metadata()]);
    }

    public function index(Request $request): JsonResponse
    {
        $query = ArmisCompetency::query()
            ->with([
                'resourceProfile.user:id,employee_id,name,initials',
                'resourceProfile.office:id,code,name',
                'competency:id,code,label,description',
                'evidenceDocumentVersion:id,document_id,version_number,original_file_name,mime_type,file_size,checksum_sha256',
            ])
            ->orderByDesc('updated_at');
        $this->service->scopeVisible($query, $request->user());

        if (! $request->boolean('includeHistory')) {
            $query->where('is_current_revision', true);
        }
        if ($request->filled('resourceProfileId')) {
            $query->where('resource_profile_id', (int) $request->input('resourceProfileId'));
        }
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        $records = $query->get();

        return response()->json([
            'success' => true,
            'data' => ArmisCompetencyResource::collection($records),
            'meta' => [
                'total' => $records->count(),
                'currentOnly' => ! $request->boolean('includeHistory'),
            ],
        ]);
    }

    public function show(Request $request, ArmisCompetency $competency): ArmisCompetencyResource
    {
        return new ArmisCompetencyResource($this->service->resolveVisible($request->user(), $competency->id));
    }

    public function events(Request $request, ArmisCompetency $competency): JsonResponse
    {
        $events = $this->service->events($request->user(), $competency);

        return response()->json([
            'success' => true,
            'data' => $events->map(fn (ArmisWorkflowEvent $event): array => [
                'id' => $event->id,
                'eventCode' => $event->event_code,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'reason' => $event->reason,
                'metadata' => $event->metadata,
                'actor' => $event->actor?->only(['id', 'employee_id', 'name', 'initials']),
                'createdAt' => $event->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return (new ArmisCompetencyResource($this->service->create($request, $this->payload($request))))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, ArmisCompetency $competency): ArmisCompetencyResource
    {
        return new ArmisCompetencyResource($this->service->update($request, $competency, $this->payload($request, true)));
    }

    public function submit(Request $request, ArmisCompetency $competency): ArmisCompetencyResource
    {
        $validated = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);

        return new ArmisCompetencyResource($this->service->submit($request, $competency, (int) $validated['lockVersion']));
    }

    public function review(Request $request, ArmisCompetency $competency): ArmisCompetencyResource
    {
        $validated = $request->validate([
            'decision' => ['required', 'string', Rule::in(['VERIFY', 'RETURN', 'REVOKE'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return new ArmisCompetencyResource($this->service->review(
            $request,
            $competency,
            $validated['decision'],
            (int) $validated['lockVersion'],
            $validated['notes'] ?? null,
        ));
    }

    public function revise(Request $request, ArmisCompetency $competency): ArmisCompetencyResource
    {
        return new ArmisCompetencyResource($this->service->revise($request, $competency, $this->payload($request, true, true)));
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, bool $update = false, bool $revision = false): array
    {
        $rules = [
            'resourceProfileId' => [$update ? 'sometimes' : 'required', 'integer', Rule::exists('armis_resource_profiles', 'id')->whereNull('deleted_at')],
            'competencyId' => [$revision ? 'sometimes' : ($update ? 'sometimes' : 'required'), 'integer', Rule::exists('master_list_items', 'id')->whereNull('deleted_at')],
            'proficiencyLevel' => ['sometimes', 'string', Rule::in(ArmisCompetency::PROFICIENCY_LEVELS)],
            'credentialType' => ['nullable', 'string', 'max:80'],
            'credentialReference' => ['nullable', 'string', 'max:120'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'issuedAt' => ['nullable', 'date'],
            'evidenceDocumentVersionId' => ['nullable', 'integer', Rule::exists('document_versions', 'id')],
            'expiresAt' => ['nullable', 'date', 'after_or_equal:issuedAt'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
        if ($update || $revision) {
            $rules['lockVersion'] = ['required', 'integer', 'min:1'];
        }

        $validated = $request->validate($rules);

        $payload = [
            'resource_profile_id' => $validated['resourceProfileId'] ?? null,
            'competency_id' => $validated['competencyId'] ?? null,
            'proficiency_level' => $validated['proficiencyLevel'] ?? null,
            'lock_version' => $validated['lockVersion'] ?? null,
        ];

        foreach ([
            'credentialType' => 'credential_type',
            'credentialReference' => 'credential_reference',
            'issuer' => 'issuer',
            'issuedAt' => 'issued_at',
            'evidenceDocumentVersionId' => 'evidence_document_version_id',
            'expiresAt' => 'expires_at',
            'notes' => 'notes',
        ] as $requestKey => $attributeKey) {
            if (array_key_exists($requestKey, $validated)) {
                $payload[$attributeKey] = $validated[$requestKey];
            }
        }

        return $payload;
    }
}
