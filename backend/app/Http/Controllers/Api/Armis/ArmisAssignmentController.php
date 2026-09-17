<?php

namespace App\Http\Controllers\Api\Armis;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArmisActualPersonDayResource;
use App\Http\Resources\ArmisAssignmentResource;
use App\Models\ArmisActualPersonDay;
use App\Models\ArmisEngagementAssignment;
use App\Services\ArmisAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Exposes ARMIS-4A assignments and actual person-day APIs. */
class ArmisAssignmentController extends Controller
{
    public function __construct(private readonly ArmisAssignmentService $service) {}

    public function metadata(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->metadata()]);
    }

    public function assignmentIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'auditEngagementId' => ['nullable', 'integer'],
            'resourceProfileId' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(ArmisEngagementAssignment::STATUSES)],
            'includeHistory' => ['nullable', 'boolean'],
        ]);
        $query = $this->service->assignmentQuery($request->user())
            ->orderByDesc('updated_at')->orderByDesc('version_number');
        if (! ($filters['includeHistory'] ?? false)) $query->where('is_current_revision', true);
        $query->when($filters['auditEngagementId'] ?? null, fn ($q, int $id) => $q->where('audit_engagement_id', $id));
        $query->when($filters['resourceProfileId'] ?? null, fn ($q, int $id) => $q->where('resource_profile_id', $id));
        $query->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status));
        $records = $query->get();

        return $this->collection(ArmisAssignmentResource::collection($records), $records->count(), (bool) ($filters['includeHistory'] ?? false));
    }

    public function assignmentShow(Request $request, int $assignment): ArmisAssignmentResource
    {
        return new ArmisAssignmentResource($this->service->resolveAssignment($request->user(), $assignment));
    }

    public function assignmentStore(Request $request): JsonResponse
    {
        return (new ArmisAssignmentResource($this->service->createAssignment($request, $this->assignmentPayload($request))))
            ->response()->setStatusCode(201);
    }

    public function assignmentUpdate(Request $request, int $assignment): ArmisAssignmentResource
    {
        return new ArmisAssignmentResource($this->service->updateAssignment(
            $request,
            $this->service->resolveAssignment($request->user(), $assignment),
            $this->assignmentPayload($request, true),
        ));
    }

    public function assignmentSubmit(Request $request, int $assignment): ArmisAssignmentResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisAssignmentResource($this->service->submitAssignment(
            $request,
            $this->service->resolveAssignment($request->user(), $assignment),
            (int) $data['lockVersion'],
        ));
    }

    public function assignmentReview(Request $request, int $assignment): ArmisAssignmentResource
    {
        $data = $this->reviewPayload($request);
        return new ArmisAssignmentResource($this->service->reviewAssignment(
            $request,
            $this->service->resolveAssignment($request->user(), $assignment),
            $data['decision'],
            (int) $data['lockVersion'],
            $data['notes'] ?? null,
        ));
    }

    public function assignmentLock(Request $request, int $assignment): ArmisAssignmentResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisAssignmentResource($this->service->lockAssignment(
            $request,
            $this->service->resolveAssignment($request->user(), $assignment),
            (int) $data['lockVersion'],
        ));
    }

    public function assignmentRevise(Request $request, int $assignment): JsonResponse
    {
        return (new ArmisAssignmentResource($this->service->reviseAssignment(
            $request,
            $this->service->resolveAssignment($request->user(), $assignment),
            $this->assignmentPayload($request, false, true),
        )))->response()->setStatusCode(201);
    }

    public function assignmentConflicts(Request $request, int $assignment): JsonResponse
    {
        $record = $this->service->resolveAssignment($request->user(), $assignment);
        return response()->json(['success' => true, 'data' => $this->service->conflicts($request->user(), $record)]);
    }

    public function actualIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'assignmentId' => ['nullable', 'integer'],
            'resourceProfileId' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(ArmisActualPersonDay::STATUSES)],
            'includeHistory' => ['nullable', 'boolean'],
        ]);
        $query = $this->service->actualQuery($request->user())
            ->with($this->actualRelations())
            ->orderByDesc('period_end')->orderByDesc('version_number');
        if (! ($filters['includeHistory'] ?? false)) $query->where('is_current_revision', true);
        $query->when($filters['assignmentId'] ?? null, fn ($q, int $id) => $q->where('assignment_id', $id));
        $query->when($filters['resourceProfileId'] ?? null, fn ($q, int $id) => $q->where('resource_profile_id', $id));
        $query->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status));
        $records = $query->get();

        return $this->collection(ArmisActualPersonDayResource::collection($records), $records->count(), (bool) ($filters['includeHistory'] ?? false));
    }

    public function actualShow(Request $request, int $actual): ArmisActualPersonDayResource
    {
        return new ArmisActualPersonDayResource($this->service->resolveActual($request->user(), $actual));
    }

    public function actualStore(Request $request): JsonResponse
    {
        return (new ArmisActualPersonDayResource($this->service->createActual($request, $this->actualPayload($request))))
            ->response()->setStatusCode(201);
    }

    public function actualUpdate(Request $request, int $actual): ArmisActualPersonDayResource
    {
        return new ArmisActualPersonDayResource($this->service->updateActual(
            $request,
            $this->service->resolveActual($request->user(), $actual),
            $this->actualPayload($request, true),
        ));
    }

    public function actualSubmit(Request $request, int $actual): ArmisActualPersonDayResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisActualPersonDayResource($this->service->submitActual(
            $request,
            $this->service->resolveActual($request->user(), $actual),
            (int) $data['lockVersion'],
        ));
    }

    public function actualReview(Request $request, int $actual): ArmisActualPersonDayResource
    {
        $data = $this->reviewPayload($request);
        return new ArmisActualPersonDayResource($this->service->reviewActual(
            $request,
            $this->service->resolveActual($request->user(), $actual),
            $data['decision'],
            (int) $data['lockVersion'],
            $data['notes'] ?? null,
        ));
    }

    public function actualLock(Request $request, int $actual): ArmisActualPersonDayResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisActualPersonDayResource($this->service->lockActual(
            $request,
            $this->service->resolveActual($request->user(), $actual),
            (int) $data['lockVersion'],
        ));
    }

    public function actualRevise(Request $request, int $actual): JsonResponse
    {
        return (new ArmisActualPersonDayResource($this->service->reviseActual(
            $request,
            $this->service->resolveActual($request->user(), $actual),
            $this->actualPayload($request, false, true),
        )))->response()->setStatusCode(201);
    }

    /** @return array<string, mixed> */
    private function assignmentPayload(Request $request, bool $update = false, bool $revision = false): array
    {
        $data = $request->validate([
            'auditEngagementId' => [$update || $revision ? 'sometimes' : 'required', 'integer'],
            'resourceProfileId' => [$update || $revision ? 'sometimes' : 'required', 'integer'],
            'requirementId' => ['nullable', 'integer'],
            'assignmentRoleCode' => [$update || $revision ? 'sometimes' : 'required', 'string', Rule::in(ArmisEngagementAssignment::ROLES)],
            'assignedFrom' => ['nullable', 'date'],
            'assignedUntil' => ['nullable', 'date', 'after_or_equal:assignedFrom'],
            'plannedPersonDays' => [$update || $revision ? 'sometimes' : 'required', 'numeric', 'gt:0', 'max:9999'],
            'requiredCompetencies' => ['nullable', 'array'],
            'requiredCompetencies.*.competencyId' => ['required_with:requiredCompetencies', 'integer'],
            'requiredCompetencies.*.minimumProficiency' => ['nullable', 'string', Rule::in(['BASIC', 'INTERMEDIATE', 'ADVANCED', 'EXPERT'])],
            'requiredCompetencies.*.notes' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lockVersion' => [$update || $revision ? 'required' : 'sometimes', 'integer', 'min:1'],
        ]);

        $payload = [
            'audit_engagement_id' => $data['auditEngagementId'] ?? null,
            'resource_profile_id' => $data['resourceProfileId'] ?? null,
            'requirement_id' => $data['requirementId'] ?? null,
            'assignment_role_code' => $data['assignmentRoleCode'] ?? null,
            'assigned_from' => $data['assignedFrom'] ?? null,
            'assigned_until' => $data['assignedUntil'] ?? null,
            'planned_person_days' => $data['plannedPersonDays'] ?? null,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : null,
            'lock_version' => $data['lockVersion'] ?? null,
        ];

        if (array_key_exists('requiredCompetencies', $data)) {
            $payload['required_competencies'] = collect($data['requiredCompetencies'])->map(fn (array $item): array => [
                'competency_id' => $item['competencyId'],
                'minimum_proficiency' => $item['minimumProficiency'] ?? 'INTERMEDIATE',
                'notes' => $item['notes'] ?? null,
            ])->all();
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function actualPayload(Request $request, bool $update = false, bool $revision = false): array
    {
        $data = $request->validate([
            'assignmentId' => [$update || $revision ? 'sometimes' : 'required', 'integer'],
            'periodStart' => [$update || $revision ? 'sometimes' : 'required', 'date'],
            'periodEnd' => [$update || $revision ? 'sometimes' : 'required', 'date', 'after_or_equal:periodStart'],
            'actualPersonDays' => [$update || $revision ? 'sometimes' : 'required', 'numeric', 'min:0', 'max:9999'],
            'varianceReason' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lockVersion' => [$update || $revision ? 'required' : 'sometimes', 'integer', 'min:1'],
        ]);

        return [
            'assignment_id' => $data['assignmentId'] ?? null,
            'period_start' => $data['periodStart'] ?? null,
            'period_end' => $data['periodEnd'] ?? null,
            'actual_person_days' => $data['actualPersonDays'] ?? null,
            'variance_reason' => array_key_exists('varianceReason', $data) ? $data['varianceReason'] : null,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : null,
            'lock_version' => $data['lockVersion'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function reviewPayload(Request $request): array
    {
        return $request->validate([
            'decision' => ['required', 'string', Rule::in(['APPROVE', 'RETURN'])],
            'lockVersion' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /** @return list<string> */
    private function actualRelations(): array
    {
        return ['assignment.engagement:id,engagement_code,title,status', 'assignment.resourceProfile.user:id,employee_id,name,initials', 'assignment.resourceProfile.office:id,code,name', 'submitter:id,employee_id,name,initials', 'reviewer:id,employee_id,name,initials', 'approver:id,employee_id,name,initials', 'supersedes:id,version_number,status'];
    }

    private function collection(mixed $collection, int $total, bool $history): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $collection->resolve(), 'meta' => ['total' => $total, 'currentOnly' => ! $history]]);
    }
}
