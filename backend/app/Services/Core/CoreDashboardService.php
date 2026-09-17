<?php

namespace App\Services\Core;

use App\Models\ArmisResourceProfile;
use App\Models\AuditEngagement;
use App\Models\CmsRecommendationCase;
use App\Models\InternalAuditPlan;
use App\Models\Office;
use App\Models\User;
use App\Services\AemsAccessService;
use App\Services\ArmisResourceService;
use App\Services\Cms\CmsRecommendationScopeService;
use App\Services\IapPlanGuard;
use Illuminate\Database\Eloquent\Builder;

/** Builds the authenticated, scope-aware landing dashboard from live records. */
class CoreDashboardService
{
    public function __construct(
        private readonly IapPlanGuard $iap,
        private readonly AemsAccessService $aems,
        private readonly CmsRecommendationScopeService $cms,
        private readonly ArmisResourceService $armis,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(User $user): array
    {
        $officeQuery = Office::query()->where('is_active', true);
        $this->scopeOffice($officeQuery, $user);

        $userQuery = User::query()->where('is_active', true);
        if (! $user->hasGlobalOfficeAccess()) {
            $userQuery->where('office_id', $user->office_id);
        }

        $plans = InternalAuditPlan::query()->where('is_active', true)->where('is_current_revision', true);
        $this->iap->scopeVisible($plans, $user);

        $engagements = $this->aems->visibleEngagements(AuditEngagement::query()->whereNull('deleted_at'), $user);
        $cmsCases = $this->cms->visibleCases(CmsRecommendationCase::query(), $user, 'cms.recommendation.view');
        $resources = $this->armis->scopeVisible(ArmisResourceProfile::query()->where('is_active', true), $user);

        $statusCounts = (clone $engagements)->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $recommendationCounts = (clone $cmsCases)->selectRaw('status_code, count(*) as aggregate')->groupBy('status_code')->pluck('aggregate', 'status_code');

        $tasks = [];
        if ($user->hasPermission('activity_logs.view')) {
            $tasks = [
                ['title' => 'Review activity and audit logs', 'code' => 'CORE-LOGS', 'due' => null, 'path' => '/activity-log'],
                ['title' => 'Review administrative reports', 'code' => 'CORE-REPORTS', 'due' => null, 'path' => '/administrative-reports'],
            ];
        }

        $recent = (clone $engagements)->with('engagementOffice:id,code,name')->latest('updated_at')->limit(5)->get()
            ->map(fn (AuditEngagement $engagement): array => [
                'number' => $engagement->engagement_code,
                'office' => $engagement->engagementOffice?->name,
                'status' => $engagement->status,
                'phase' => $engagement->phase,
                'updatedAt' => $engagement->updated_at?->toIso8601String(),
                'path' => '/audit-engagement-management/engagements/'.$engagement->id,
            ])->values();

        return [
            'asOf' => now()->toIso8601String(),
            'scope' => [
                'office' => $user->hasGlobalOfficeAccess() ? 'ALL' : ($user->office_id ? (int) $user->office_id : null),
                'engagement' => $user->hasGlobalEngagementAccess() ? 'ALL' : 'ASSIGNED',
            ],
            'modules' => [
                ['key' => 'iap', 'code' => 'IAP', 'label' => 'Internal Audit Planning', 'value' => (int) $plans->count(), 'note' => 'Active plans', 'path' => '/internal-audit-planning/dashboard', 'tone' => 'blue'],
                ['key' => 'aems', 'code' => 'AEMS', 'label' => 'Audit Engagement Monitoring', 'value' => (int) (clone $engagements)->whereNotIn('status', ['CLOSED', 'CANCELLED'])->count(), 'note' => 'Active engagements', 'path' => '/audit-engagement-management/dashboard', 'tone' => 'green'],
                ['key' => 'afr', 'code' => 'AFR', 'label' => 'Audit Finding & Recommendation', 'value' => (int) (clone $engagements)->whereIn('status', ['FINDINGS_COMMUNICATION', 'REPORTING', 'ISSUED'])->count(), 'note' => 'Engagements reporting findings', 'path' => '/audit-engagement-management/findings', 'tone' => 'orange'],
                ['key' => 'cms', 'code' => 'CMS', 'label' => 'Compliance Management', 'value' => (int) $recommendationCounts->sum(), 'note' => 'Visible recommendations', 'path' => '/compliance-management/dashboard', 'tone' => 'purple'],
                ['key' => 'armis', 'code' => 'ARMIS', 'label' => 'Audit Resource Management', 'value' => (int) $resources->count(), 'note' => 'Active resources', 'path' => '/audit-resource-management/resources', 'tone' => 'teal'],
                ['key' => 'ais', 'code' => 'AIS', 'label' => 'Audit Intelligence System', 'value' => null, 'note' => 'Not enabled', 'path' => '/audit-intelligence-system', 'tone' => 'yellow', 'available' => false],
            ],
            'tasks' => $tasks,
            'upcomingActivities' => [],
            'recentEngagements' => $recent,
            'engagementStatus' => $statusCounts,
            'recommendationStatus' => $recommendationCounts,
            'overdueRecommendations' => (int) (clone $cmsCases)->whereNotNull('effective_target_implementation_date')->where('effective_target_implementation_date', '<', today())->whereNotIn('status_code', ['CLOSED', 'ACCEPTED_RISK', 'NO_LONGER_APPLICABLE'])->count(),
            'quickActions' => collect([
                ['label' => 'Open Audit Engagements', 'path' => '/audit-engagement-management/engagements', 'permission' => 'aems.engagement.view'],
                ['label' => 'Search Documents', 'path' => '/document-management', 'permission' => 'documents.view'],
                ['label' => 'Administrative Reports', 'path' => '/administrative-reports', 'permission' => 'administrative_reports.view'],
            ])->filter(fn (array $action): bool => $user->hasPermission($action['permission']))->values(),
        ];
    }

    private function scopeOffice(Builder $query, User $user): void
    {
        if (! $user->hasGlobalOfficeAccess()) {
            $query->whereKey($user->office_id ?: 0);
        }
    }
}
