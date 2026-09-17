<?php

namespace App\Http\Controllers\Api\Armis;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Http\Controllers\Controller;
use App\Http\Resources\ArmisAvailabilityResource;
use App\Http\Resources\ArmisCapacityResource;
use App\Http\Resources\ArmisWorkloadResource;
use App\Models\ArmisAvailabilityPeriod;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisWorkloadAllocation;
use App\Services\ArmisPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Exposes ARMIS-3A/3B availability, capacity, workload, and utilization APIs. */
class ArmisPlanningController extends Controller
{
    public function __construct(private readonly ArmisPlanningService $service) {}

    public function metadata(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'statuses' => collect(ArmisAvailabilityPeriod::STATUSES)->map(fn (string $code): array => [
                    'code' => $code, 'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
                ])->values(),
                'availabilityTypes' => collect(ArmisAvailabilityPeriod::TYPES)->map(fn (string $code): array => [
                    'code' => $code, 'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
                ])->values(),
                'fiscalYears' => range((int) now()->year - 1, (int) now()->year + 5),
                'reviewDecisions' => [
                    ['code' => 'APPROVE', 'label' => 'Approve'],
                    ['code' => 'RETURN', 'label' => 'Return'],
                ],
                'workflow' => [
                    'editableStatuses' => ['DRAFT', 'RETURNED'],
                    'reviewStatus' => 'SUBMITTED',
                    'approvedStatus' => 'APPROVED',
                    'lockedStatus' => 'LOCKED',
                ],
                'provider' => app(ResourcePlanningGateway::class)->status(),
            ],
        ]);
    }

    public function availabilityIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'resourceProfileId' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', Rule::in(ArmisAvailabilityPeriod::STATUSES)],
            'availabilityType' => ['nullable', 'string', Rule::in(ArmisAvailabilityPeriod::TYPES)],
            'includeHistory' => ['nullable', 'boolean'],
        ]);
        $query = $this->service->availabilityQuery($request->user())->with([
            'resourceProfile.user:id,employee_id,name,initials', 'resourceProfile.office:id,code,name',
            'submitter:id,employee_id,name,initials', 'reviewer:id,employee_id,name,initials',
            'approver:id,employee_id,name,initials',
        ])->orderBy('start_date')->orderBy('id');
        if (! ($filters['includeHistory'] ?? false)) $query->where('is_current_revision', true);
        $query->when($filters['resourceProfileId'] ?? null, fn ($q, int $id) => $q->where('resource_profile_id', $id));
        $query->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status));
        $query->when($filters['availabilityType'] ?? null, fn ($q, string $type) => $q->where('availability_type', $type));
        $records = $query->get();

        return $this->collectionResponse(ArmisAvailabilityResource::collection($records), $records->count(), (bool) ($filters['includeHistory'] ?? false));
    }

    public function availabilityShow(Request $request, int $availability): ArmisAvailabilityResource
    {
        return new ArmisAvailabilityResource($this->service->resolveAvailability($request->user(), $availability));
    }

    public function availabilityStore(Request $request): JsonResponse
    {
        return (new ArmisAvailabilityResource($this->service->createAvailability($request, $this->availabilityPayload($request))))->response()->setStatusCode(201);
    }

    public function availabilityUpdate(Request $request, int $availability): ArmisAvailabilityResource
    {
        return new ArmisAvailabilityResource($this->service->updateAvailability($request, $this->service->resolveAvailability($request->user(), $availability), $this->availabilityPayload($request, true)));
    }

    public function availabilitySubmit(Request $request, int $availability): ArmisAvailabilityResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisAvailabilityResource($this->service->submitAvailability($request, $this->service->resolveAvailability($request->user(), $availability), (int) $data['lockVersion']));
    }

    public function availabilityRevise(Request $request, int $availability): JsonResponse
    {
        return (new ArmisAvailabilityResource($this->service->reviseAvailability($request, $this->service->resolveAvailability($request->user(), $availability), $this->availabilityPayload($request, true))))->response()->setStatusCode(201);
    }

    public function availabilityReview(Request $request, int $availability): ArmisAvailabilityResource
    {
        $data = $this->reviewPayload($request);
        return new ArmisAvailabilityResource($this->service->reviewAvailability($request, $this->service->resolveAvailability($request->user(), $availability), $data['decision'], (int) $data['lockVersion'], $data['notes'] ?? null));
    }

    public function availabilityLock(Request $request, int $availability): ArmisAvailabilityResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisAvailabilityResource($this->service->lockAvailability($request, $this->service->resolveAvailability($request->user(), $availability), (int) $data['lockVersion']));
    }

    public function capacityIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'resourceProfileId' => ['nullable', 'integer'], 'fiscalYear' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'status' => ['nullable', 'string', Rule::in(ArmisCapacitySubmission::STATUSES)], 'includeHistory' => ['nullable', 'boolean'],
        ]);
        $query = $this->service->capacityQuery($request->user())->with([
            'resourceProfile.user:id,employee_id,name,initials', 'resourceProfile.office:id,code,name',
            'submitter:id,employee_id,name,initials', 'reviewer:id,employee_id,name,initials', 'approver:id,employee_id,name,initials',
        ])->orderByDesc('fiscal_year')->orderByDesc('version_number');
        if (! ($filters['includeHistory'] ?? false)) $query->where('is_current_revision', true);
        $query->when($filters['resourceProfileId'] ?? null, fn ($q, int $id) => $q->where('resource_profile_id', $id));
        $query->when($filters['fiscalYear'] ?? null, fn ($q, int $year) => $q->where('fiscal_year', $year));
        $query->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status));
        $records = $query->get();

        return $this->collectionResponse(ArmisCapacityResource::collection($records), $records->count(), (bool) ($filters['includeHistory'] ?? false));
    }

    public function capacityShow(Request $request, int $capacity): ArmisCapacityResource
    {
        return new ArmisCapacityResource($this->service->resolveCapacity($request->user(), $capacity));
    }

    public function capacityStore(Request $request): JsonResponse
    {
        return (new ArmisCapacityResource($this->service->createCapacity($request, $this->capacityPayload($request))))->response()->setStatusCode(201);
    }

    public function capacityUpdate(Request $request, int $capacity): ArmisCapacityResource
    {
        return new ArmisCapacityResource($this->service->updateCapacity($request, $this->service->resolveCapacity($request->user(), $capacity), $this->capacityPayload($request, true)));
    }

    public function capacitySubmit(Request $request, int $capacity): ArmisCapacityResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisCapacityResource($this->service->submitCapacity($request, $this->service->resolveCapacity($request->user(), $capacity), (int) $data['lockVersion']));
    }

    public function capacityReview(Request $request, int $capacity): ArmisCapacityResource
    {
        $data = $this->reviewPayload($request);
        return new ArmisCapacityResource($this->service->reviewCapacity($request, $this->service->resolveCapacity($request->user(), $capacity), $data['decision'], (int) $data['lockVersion'], $data['notes'] ?? null));
    }

    public function capacityLock(Request $request, int $capacity): ArmisCapacityResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisCapacityResource($this->service->lockCapacity($request, $this->service->resolveCapacity($request->user(), $capacity), (int) $data['lockVersion']));
    }

    public function workloadIndex(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'resourceProfileId' => ['nullable', 'integer'], 'requirementId' => ['nullable', 'integer'],
            'fiscalYear' => ['nullable', 'integer', 'min:2000', 'max:2100'], 'status' => ['nullable', 'string', Rule::in(ArmisWorkloadAllocation::STATUSES)],
            'includeHistory' => ['nullable', 'boolean'],
        ]);
        $query = $this->service->workloadQuery($request->user())->with([
            'resourceProfile.user:id,employee_id,name,initials', 'resourceProfile.office:id,code,name', 'requirement:id,title,status',
            'submitter:id,employee_id,name,initials', 'reviewer:id,employee_id,name,initials', 'approver:id,employee_id,name,initials',
        ])->orderByDesc('fiscal_year')->orderByDesc('updated_at');
        if (! ($filters['includeHistory'] ?? false)) $query->where('is_current_revision', true);
        $query->when($filters['resourceProfileId'] ?? null, fn ($q, int $id) => $q->where('resource_profile_id', $id));
        $query->when($filters['requirementId'] ?? null, fn ($q, int $id) => $q->where('requirement_id', $id));
        $query->when($filters['fiscalYear'] ?? null, fn ($q, int $year) => $q->where('fiscal_year', $year));
        $query->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status));
        $records = $query->get();

        return $this->collectionResponse(ArmisWorkloadResource::collection($records), $records->count(), (bool) ($filters['includeHistory'] ?? false));
    }

    public function workloadShow(Request $request, int $workload): ArmisWorkloadResource
    {
        return new ArmisWorkloadResource($this->service->resolveWorkload($request->user(), $workload));
    }

    public function workloadStore(Request $request): JsonResponse
    {
        return (new ArmisWorkloadResource($this->service->createWorkload($request, $this->workloadPayload($request))))->response()->setStatusCode(201);
    }

    public function workloadUpdate(Request $request, int $workload): ArmisWorkloadResource
    {
        return new ArmisWorkloadResource($this->service->updateWorkload($request, $this->service->resolveWorkload($request->user(), $workload), $this->workloadPayload($request, true)));
    }

    public function workloadSubmit(Request $request, int $workload): ArmisWorkloadResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisWorkloadResource($this->service->submitWorkload($request, $this->service->resolveWorkload($request->user(), $workload), (int) $data['lockVersion']));
    }

    public function workloadReview(Request $request, int $workload): ArmisWorkloadResource
    {
        $data = $this->reviewPayload($request);
        return new ArmisWorkloadResource($this->service->reviewWorkload($request, $this->service->resolveWorkload($request->user(), $workload), $data['decision'], (int) $data['lockVersion'], $data['notes'] ?? null));
    }

    public function workloadLock(Request $request, int $workload): ArmisWorkloadResource
    {
        $data = $request->validate(['lockVersion' => ['required', 'integer', 'min:1']]);
        return new ArmisWorkloadResource($this->service->lockWorkload($request, $this->service->resolveWorkload($request->user(), $workload), (int) $data['lockVersion']));
    }

    public function utilization(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fiscalYear' => ['required', 'integer', 'min:2000', 'max:2100'], 'resourceProfileId' => ['nullable', 'integer'],
        ]);
        return response()->json(['success' => true, 'data' => $this->service->utilization($request->user(), (int) $data['fiscalYear'], isset($data['resourceProfileId']) ? (int) $data['resourceProfileId'] : null)]);
    }

    /** @return array<string, mixed> */
    private function availabilityPayload(Request $request, bool $update = false): array
    {
        $data = $request->validate([
            'resourceProfileId' => [$update ? 'sometimes' : 'required', 'integer'],
            'availabilityType' => [$update ? 'sometimes' : 'required', 'string', Rule::in(ArmisAvailabilityPeriod::TYPES)],
            'startDate' => [$update ? 'sometimes' : 'required', 'date'],
            'endDate' => [$update ? 'sometimes' : 'required', 'date'],
            'personDays' => ['nullable', 'numeric', 'min:0'], 'notes' => ['nullable', 'string', 'max:5000'],
            'lockVersion' => [$update ? 'required' : 'sometimes', 'integer', 'min:1'],
        ]);
        return [
            'resource_profile_id' => $data['resourceProfileId'] ?? null, 'availability_type' => $data['availabilityType'] ?? null,
            'start_date' => $data['startDate'] ?? null, 'end_date' => $data['endDate'] ?? null,
            'person_days' => array_key_exists('personDays', $data) ? $data['personDays'] : null,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : null, 'lock_version' => $data['lockVersion'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function capacityPayload(Request $request, bool $update = false): array
    {
        $data = $request->validate([
            'resourceProfileId' => [$update ? 'sometimes' : 'required', 'integer'],
            'fiscalYear' => [$update ? 'sometimes' : 'required', 'integer', 'min:2000', 'max:2100'],
            'availablePersonDays' => [$update ? 'sometimes' : 'required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'], 'lockVersion' => [$update ? 'required' : 'sometimes', 'integer', 'min:1'],
        ]);
        return [
            'resource_profile_id' => $data['resourceProfileId'] ?? null, 'fiscal_year' => $data['fiscalYear'] ?? null,
            'available_person_days' => $data['availablePersonDays'] ?? null, 'notes' => array_key_exists('notes', $data) ? $data['notes'] : null,
            'lock_version' => $data['lockVersion'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function workloadPayload(Request $request, bool $update = false): array
    {
        $data = $request->validate([
            'resourceProfileId' => [$update ? 'sometimes' : 'required', 'integer'], 'requirementId' => ['nullable', 'integer'],
            'sourceModule' => ['sometimes', 'string', 'max:30'], 'sourceType' => [$update ? 'sometimes' : 'required', 'string', 'max:60'],
            'sourceId' => ['nullable', 'integer'], 'fiscalYear' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'plannedPersonDays' => [$update ? 'sometimes' : 'required', 'numeric', 'gt:0'], 'notes' => ['nullable', 'string', 'max:5000'],
            'lockVersion' => [$update ? 'required' : 'sometimes', 'integer', 'min:1'],
        ]);
        return [
            'resource_profile_id' => $data['resourceProfileId'] ?? null, 'requirement_id' => $data['requirementId'] ?? null,
            'source_module' => $data['sourceModule'] ?? 'ARMIS', 'source_type' => $data['sourceType'] ?? null,
            'source_id' => $data['sourceId'] ?? null, 'fiscal_year' => $data['fiscalYear'] ?? null,
            'planned_person_days' => $data['plannedPersonDays'] ?? null, 'notes' => array_key_exists('notes', $data) ? $data['notes'] : null,
            'lock_version' => $data['lockVersion'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function reviewPayload(Request $request): array
    {
        return $request->validate([
            'decision' => ['required', 'string', Rule::in(['APPROVE', 'RETURN'])],
            'lockVersion' => ['required', 'integer', 'min:1'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function collectionResponse(mixed $collection, int $total, bool $history): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $collection->resolve(), 'meta' => ['total' => $total, 'currentOnly' => ! $history]]);
    }
}
