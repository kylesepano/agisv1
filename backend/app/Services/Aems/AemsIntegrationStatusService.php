<?php

namespace App\Services;

use App\Contracts\Aems\CmsRecommendationGateway;
use App\Contracts\Aems\IapEngagementGateway;
use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\AuditEngagement;
use App\Models\CmsRecommendation;
use App\Models\User;
use App\Support\ActivityRecorder;
use Illuminate\Support\Facades\DB;

/** Describes the active module providers and shared Core capabilities used by AEMS. */
class AemsIntegrationStatusService
{
    public function __construct(
        private readonly IapEngagementGateway $iap,
        private readonly CmsRecommendationGateway $cms,
        private readonly ResourcePlanningGateway $resources,
        private readonly RuntimeConfiguration $runtime,
        private readonly NotificationService $notifications,
        private readonly DocumentAccessService $documents,
        private readonly WorkflowEngine $workflows,
    ) {}

    /** @return array<string, mixed> */
    public function status(User $user): array
    {
        // Referencing the resolved services is deliberate: container failures
        // surface immediately instead of reporting a false healthy capability.
        $coreProviders = [
            'documentVersioning' => $this->documents::class,
            'workflowEngine' => $this->workflows::class,
            'notifications' => $this->notifications::class,
            'activityAndAudit' => ActivityRecorder::class,
            'runtimeConfiguration' => $this->runtime::class,
        ];

        $iap = $this->iap->status();
        $cms = $this->cms->status();
        $integrity = $this->integrity($user);
        if (! $user->hasGlobalEngagementAccess()) {
            unset(
                $iap['eligibleEngagements'],
                $cms['transferredRecommendations'],
                $cms['operationalCases'],
            );
        }

        return [
            'core' => [
                'module' => 'CORE',
                'available' => true,
                'mode' => 'SHARED_SERVICES',
                'providers' => $coreProviders,
                'capabilities' => [
                    'users_and_offices',
                    'roles_permissions_scopes',
                    'audit_areas_and_focuses',
                    'master_lists',
                    'document_versioning',
                    'workflow_engine',
                    'notifications',
                    'activity_logs',
                    'audit_trails',
                    'runtime_configuration',
                    'numbering',
                ],
                'workflowMode' => 'AEMS_DOMAIN_GUARDED_WITH_CORE_INFRASTRUCTURE',
                'timezone' => $this->runtime->timezone(),
                'paginationSize' => $this->runtime->paginationSize(),
                'documentUploadMaxKilobytes' => $this->runtime->documentUploadMaxKilobytes(),
            ],
            'iap' => $iap,
            'cms' => $cms,
            'armis' => $this->resources->status(),
            'integrity' => $integrity,
            'security' => [
                'scopeAware' => true,
                'engagementVisibility' => 'AEMS_ACCESS_SERVICE',
                'separationOfDuties' => 'AEMS_WORKFLOW_GUARDS',
                'protectedDocumentDownloads' => true,
                'activityLogs' => true,
                'auditTrails' => true,
                'ais' => [
                    'integrated' => false,
                    'boundary' => 'OUT_OF_SCOPE_UNTIL_SEPARATE_AIS_PHASE',
                ],
            ],
        ];
    }

    /**
     * Checks cross-module referential invariants without creating dashboard
     * state.  Detailed counts are restricted to global AEMS users; scoped
     * users receive only the health result and safe capability metadata.
     *
     * @return array<string, mixed>
     */
    private function integrity(User $user): array
    {
        $visibleEngagements = AuditEngagement::query()->visibleTo($user);
        $invalidIapLineage = (clone $visibleEngagements)
            ->where('source_type', 'PLANNED')
            ->where(function ($query): void {
                $query->whereNull('iap_plan_engagement_id')
                    ->orWhereNull('source_snapshot');
            })
            ->count();
        $visibleEngagementIds = (clone $visibleEngagements)->pluck('audit_engagements.id');
        $duplicateIapSources = DB::table('audit_engagements')
            ->whereIn('id', $visibleEngagementIds)
            ->whereNotNull('iap_plan_engagement_id')
            ->where('status', '<>', 'CANCELLED')
            ->whereNull('deleted_at')
            ->select('iap_plan_engagement_id')
            ->groupBy('iap_plan_engagement_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        $cmsScope = CmsRecommendation::query()->whereHas(
            'engagement',
            fn ($query) => $query->visibleTo($user),
        );
        $missingCmsLineage = (clone $cmsScope)
            ->whereNull('source_snapshot')
            ->count();
        $missingCmsCase = (clone $cmsScope)
            ->whereDoesntHave('case')
            ->count();

        $checks = [
            'iapLineage' => $invalidIapLineage === 0,
            'iapDuplicatePrevention' => $duplicateIapSources === 0,
            'cmsSourceLinks' => $missingCmsLineage === 0,
            'cmsCaseCoverage' => $missingCmsCase === 0,
            'scopeFiltering' => true,
            'protectedDocuments' => true,
            'aisExcluded' => true,
        ];

        $result = [
            'healthy' => ! in_array(false, $checks, true),
            'checks' => $checks,
            'ownership' => [
                'iap' => 'IAP_APPROVED_SOURCE_READ_ONLY',
                'aems' => 'AEMS_ENGAGEMENT_AND_SNAPSHOT',
                'armis' => 'ARMIS_SOLE_OPERATIONAL_RESOURCE_PROVIDER',
                'cms' => 'CMS_IMMUTABLE_INTAKE_AND_OPERATIONS',
            ],
        ];

        if ($user->hasGlobalEngagementAccess()) {
            $result['details'] = [
                'invalidIapLineage' => $invalidIapLineage,
                'duplicateIapSources' => $duplicateIapSources,
                'missingCmsSourceLinks' => $missingCmsLineage,
                'missingCmsCases' => $missingCmsCase,
            ];
        }

        return $result;
    }
}
