<?php

namespace App\Http\Controllers\Api\Armis;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Http\Controllers\Controller;
use App\Models\ArmisActualPersonDay;
use App\Models\ArmisAvailabilityPeriod;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisCompetency;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisResourceRequirement;
use App\Models\ArmisWorkloadAllocation;
use App\Services\ArmisResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Returns a scope-aware read snapshot of the ARMIS-1A normalized foundation. */
class ArmisFoundationController extends Controller
{
    public function __construct(private readonly ArmisResourceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $profileQuery = ArmisResourceProfile::query()
            ->with(['user:id,employee_id,name,position,is_active', 'office:id,code,name'])
            ->orderBy('resource_code');
        $this->service->scopeVisible($profileQuery, $request->user());
        $profiles = $profileQuery->get();
        $profileIds = $profiles->modelKeys();

        $competencies = ArmisCompetency::query()
            ->whereIn('resource_profile_id', $profileIds)
            ->where('is_current_revision', true)
            ->with('competency:id,code,label')
            ->orderBy('resource_profile_id')
            ->get();
        $availability = ArmisAvailabilityPeriod::query()
            ->whereIn('resource_profile_id', $profileIds)
            ->where('is_current_revision', true)
            ->orderBy('start_date')
            ->get();
        $capacities = ArmisCapacitySubmission::query()
            ->whereIn('resource_profile_id', $profileIds)
            ->where('is_current_revision', true)
            ->orderByDesc('fiscal_year')
            ->orderByDesc('version_number')
            ->get();
        $workload = ArmisWorkloadAllocation::query()
            ->whereIn('resource_profile_id', $profileIds)
            ->where('is_current_revision', true)
            ->orderByDesc('fiscal_year')
            ->get();
        $actuals = ArmisActualPersonDay::query()
            ->whereIn('resource_profile_id', $profileIds)
            ->orderByDesc('period_start')
            ->get();

        $requirementsQuery = ArmisResourceRequirement::query()
            ->with('competencies.competency:id,code,label')
            ->orderByDesc('fiscal_year')
            ->orderBy('title');
        if (! $request->user()->hasGlobalOfficeAccess()) {
            $requirementsQuery->where(function ($query) use ($request): void {
                $query->where('office_id', $request->user()->office_id)->orWhereNull('office_id');
            });
        }
        $requirements = $requirementsQuery->get();

        return response()->json([
            'success' => true,
            'data' => [
                'profiles' => $profiles->map(fn (ArmisResourceProfile $profile): array => [
                    'id' => $profile->id,
                    'resourceCode' => $profile->resource_code,
                    'userId' => $profile->user_id,
                    'officeId' => $profile->office_id,
                    'category' => $profile->category,
                    'status' => $profile->status,
                    'lockVersion' => $profile->lock_version,
                    'user' => $profile->user ? [
                        'id' => $profile->user->id,
                        'employeeId' => $profile->user->employee_id,
                        'name' => $profile->user->name,
                        'position' => $profile->user->position,
                        'isActive' => $profile->user->is_active,
                    ] : null,
                    'office' => $profile->office ? [
                        'id' => $profile->office->id,
                        'code' => $profile->office->code,
                        'name' => $profile->office->name,
                    ] : null,
                ])->values(),
                'competencies' => $competencies->map(fn (ArmisCompetency $item): array => [
                    'id' => $item->id,
                    'competencyFamilyUuid' => $item->competency_family_uuid,
                    'resourceProfileId' => $item->resource_profile_id,
                    'competencyId' => $item->competency_id,
                    'code' => $item->competency?->code,
                    'label' => $item->competency?->label,
                    'proficiencyLevel' => $item->proficiency_level,
                    'versionNumber' => $item->version_number,
                    'isCurrentRevision' => (bool) $item->is_current_revision,
                    'credentialType' => $item->credential_type,
                    'credentialReference' => $item->credential_reference,
                    'issuer' => $item->issuer,
                    'issuedAt' => $item->issued_at?->toDateString(),
                    'status' => $item->status,
                    'evidenceDocumentVersionId' => $item->evidence_document_version_id,
                    'expiresAt' => $item->expires_at?->toDateString(),
                    'lockVersion' => $item->lock_version,
                ])->values(),
                'availability' => $availability->map(fn (ArmisAvailabilityPeriod $item): array => [
                    'id' => $item->id,
                    'availabilityFamilyUuid' => $item->availability_family_uuid,
                    'resourceProfileId' => $item->resource_profile_id,
                    'versionNumber' => $item->version_number,
                    'isCurrentRevision' => (bool) $item->is_current_revision,
                    'availabilityType' => $item->availability_type,
                    'startDate' => $item->start_date?->toDateString(),
                    'endDate' => $item->end_date?->toDateString(),
                    'personDays' => $item->person_days !== null ? (float) $item->person_days : null,
                    'status' => $item->status,
                    'lockVersion' => $item->lock_version,
                ])->values(),
                'capacities' => $capacities->map(fn (ArmisCapacitySubmission $item): array => [
                    'id' => $item->id,
                    'resourceProfileId' => $item->resource_profile_id,
                    'fiscalYear' => $item->fiscal_year,
                    'versionNumber' => $item->version_number,
                    'availablePersonDays' => (float) $item->available_person_days,
                    'status' => $item->status,
                    'supersedesId' => $item->supersedes_id,
                    'isCurrentRevision' => (bool) $item->is_current_revision,
                    'lockVersion' => $item->lock_version,
                ])->values(),
                'requirements' => $requirements->map(fn (ArmisResourceRequirement $item): array => [
                    'id' => $item->id,
                    'sourceModule' => $item->source_module,
                    'sourceType' => $item->source_type,
                    'sourceId' => $item->source_id,
                    'officeId' => $item->office_id,
                    'fiscalYear' => $item->fiscal_year,
                    'title' => $item->title,
                    'requiredPersonDays' => (float) $item->required_person_days,
                    'status' => $item->status,
                    'competencies' => $item->competencies->map(fn ($requirement): array => [
                        'id' => $requirement->id,
                        'competencyId' => $requirement->competency_id,
                        'code' => $requirement->competency?->code,
                        'label' => $requirement->competency?->label,
                        'minimumResources' => $requirement->minimum_resources,
                        'minimumProficiency' => $requirement->minimum_proficiency,
                    ])->values(),
                ])->values(),
                'workload' => $workload->map(fn (ArmisWorkloadAllocation $item): array => [
                    'id' => $item->id,
                    'workloadFamilyUuid' => $item->workload_family_uuid,
                    'resourceProfileId' => $item->resource_profile_id,
                    'versionNumber' => $item->version_number,
                    'supersedesId' => $item->supersedes_id,
                    'isCurrentRevision' => (bool) $item->is_current_revision,
                    'requirementId' => $item->requirement_id,
                    'sourceModule' => $item->source_module,
                    'sourceType' => $item->source_type,
                    'sourceId' => $item->source_id,
                    'fiscalYear' => $item->fiscal_year,
                    'plannedPersonDays' => (float) $item->planned_person_days,
                    'status' => $item->status,
                    'lockVersion' => $item->lock_version,
                ])->values(),
                'actuals' => $actuals->map(fn (ArmisActualPersonDay $item): array => [
                    'id' => $item->id,
                    'resourceProfileId' => $item->resource_profile_id,
                    'sourceModule' => $item->source_module,
                    'sourceType' => $item->source_type,
                    'sourceId' => $item->source_id,
                    'periodStart' => $item->period_start?->toDateString(),
                    'periodEnd' => $item->period_end?->toDateString(),
                    'versionNumber' => $item->version_number,
                    'actualPersonDays' => (float) $item->actual_person_days,
                    'status' => $item->status,
                    'supersedesId' => $item->supersedes_id,
                    'lockVersion' => $item->lock_version,
                ])->values(),
            ],
            'meta' => [
                'profileCount' => $profiles->count(),
                'competencyCount' => $competencies->count(),
                'availabilityCount' => $availability->count(),
                'capacityCount' => $capacities->count(),
                'requirementCount' => $requirements->count(),
                'workloadCount' => $workload->count(),
                'actualCount' => $actuals->count(),
                'provider' => app(ResourcePlanningGateway::class)->status(),
            ],
        ]);
    }
}
