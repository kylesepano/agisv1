<?php

namespace App\Services;

use App\Models\AuditArea;
use App\Models\CmsEscalationNoticeVersion;
use App\Models\CmsEscalationResponseVersion;
use App\Models\CmsProgressUpdateVersion;
use App\Models\CmsRecommendationCase;
use App\Models\CmsTargetDateExtensionVersion;
use App\Models\CmsValidationVersion;
use App\Models\IapAuditUniverseItem;
use App\Models\IapPlanEngagement;
use App\Models\InternalAuditPlan;
use App\Models\Office;
use App\Models\StrategicInternalAuditPlan;
use App\Models\User;
use App\Services\Cms\CmsRecommendationScopeService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Defines the supported module record types to which documents may be linked.
 */
class DocumentLinkRegistry
{
    /** @var array<string, string> */
    public const MODULES = [
        'CORE' => 'AGIS Core',
        'IAP' => 'Internal Audit Planning',
        'AEM' => 'Audit Engagement Management',
        'AFR' => 'Audit Findings and Recommendations',
        'CMS' => 'Compliance Management',
        'ARMS' => 'Audit Resource Management',
        'AIS' => 'Audit Intelligence System',
    ];

    public function __construct(
        private readonly IapPlanGuard $iapGuard,
        private readonly SiapPlanGuard $siapGuard,
        private readonly CmsRecommendationScopeService $cmsScope,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function options(User $user): Collection
    {
        $options = collect(self::MODULES)
            ->map(fn (string $label, string $module): array => $this->option(
                $module,
                'MODULE',
                0,
                $module,
                "{$label} — Entire module",
            ))
            ->values();

        $offices = Office::query()
            ->when(
                ! $user->hasGlobalOfficeAccess(),
                fn ($query) => $query->whereKey($user->office_id),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $options->push(...$offices->map(fn (Office $office): array => $this->option(
            'CORE',
            'OFFICE',
            $office->id,
            $office->code,
            "{$office->code} — {$office->name}",
        )));

        $areas = AuditArea::query()
            ->when(
                ! $user->hasGlobalOfficeAccess(),
                fn ($query) => $query->where(function ($query) use ($user): void {
                    $query
                        ->where('responsible_office_id', $user->office_id)
                        ->orWhereHas('offices', fn ($offices) => $offices->whereKey($user->office_id));
                }),
            )
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
        $options->push(...$areas->map(fn (AuditArea $area): array => $this->option(
            'CORE',
            'AUDIT_AREA',
            $area->id,
            $area->code,
            "{$area->code} — {$area->name}",
        )));

        $annualPlans = $this->iapGuard
            ->scopeVisible(InternalAuditPlan::query(), $user)
            ->orderByDesc('fiscal_year')
            ->get(['id', 'plan_code', 'title']);
        $options->push(...$annualPlans->map(fn (InternalAuditPlan $plan): array => $this->option(
            'IAP',
            'ANNUAL_PLAN',
            $plan->id,
            $plan->plan_code,
            "{$plan->plan_code} — {$plan->title}",
        )));

        $strategicPlans = $this->siapGuard
            ->scopeVisible(StrategicInternalAuditPlan::query(), $user)
            ->orderByDesc('start_year')
            ->get(['id', 'plan_code', 'title']);
        $options->push(...$strategicPlans->map(fn (StrategicInternalAuditPlan $plan): array => $this->option(
            'IAP',
            'STRATEGIC_PLAN',
            $plan->id,
            $plan->plan_code,
            "{$plan->plan_code} — {$plan->title}",
        )));

        $visiblePlanIds = $annualPlans->pluck('id');
        $engagements = IapPlanEngagement::query()
            ->whereIn('plan_id', $visiblePlanIds)
            ->orderBy('engagement_code')
            ->get(['id', 'engagement_code', 'title']);
        $options->push(...$engagements->map(fn (IapPlanEngagement $engagement): array => $this->option(
            'IAP',
            'PLAN_ENGAGEMENT',
            $engagement->id,
            $engagement->engagement_code,
            "{$engagement->engagement_code} — {$engagement->title}",
        )));

        $universe = IapAuditUniverseItem::query()
            ->when(
                ! $user->hasGlobalOfficeAccess(),
                fn ($query) => $query->where(function ($query) use ($user): void {
                    $query
                        ->where('responsible_office_id', $user->office_id)
                        ->orWhereHas(
                            'stakeholderOffices',
                            fn ($offices) => $offices->whereKey($user->office_id),
                        );
                }),
            )
            ->orderBy('name')
            ->get(['id', 'subject_code', 'name']);
        $options->push(...$universe->map(fn (IapAuditUniverseItem $item): array => $this->option(
            'IAP',
            'AUDIT_UNIVERSE',
            $item->id,
            $item->subject_code,
            "{$item->subject_code} — {$item->name}",
        )));

        if ($user->hasPermission('cms.evidence.upload')) {
            $visibleCaseIds = $this->cmsScope
                ->visibleCases(
                    CmsRecommendationCase::query(),
                    $user,
                    'cms.progress.view',
                )
                ->pluck('id');
            $progressVersions = CmsProgressUpdateVersion::query()
                ->where('status_code', 'DRAFT')
                ->whereHas(
                    'progressUpdate',
                    fn ($query) => $query->whereIn(
                        'cms_recommendation_case_id',
                        $visibleCaseIds,
                    ),
                )
                ->with(['progressUpdate', 'milestoneProgress'])
                ->orderByDesc('id')
                ->limit(250)
                ->get();
            foreach ($progressVersions as $version) {
                $family = $version->progressUpdate;
                $code = sprintf(
                    'CMS-UPD-%06d-%03d-V%d',
                    $family->cms_recommendation_case_id,
                    $family->reporting_sequence,
                    $version->version_number,
                );
                $options->push($this->option(
                    'CMS',
                    'PROGRESS_UPDATE_VERSION',
                    $version->id,
                    $code,
                    "{$code} â€” Management-reported Progress Update",
                ));
                $options->push(...$version->milestoneProgress->map(
                    fn ($progress): array => $this->option(
                        'CMS',
                        'MILESTONE_PROGRESS',
                        $progress->id,
                        "CMS-MPR-{$progress->id}",
                        "{$code} â€” Milestone {$progress->milestone_sequence}",
                    ),
                ));
            }
        }

        if ($user->hasPermission('cms.validation-evidence.upload')) {
            $validationVersions = CmsValidationVersion::query()
                ->where('status_code', 'DRAFT')
                ->whereHas(
                    'review.currentAssignment',
                    fn ($assignment) => $assignment
                        ->where('user_id', $user->id)
                        ->where('is_current', true),
                )
                ->with(['review', 'items'])
                ->orderByDesc('id')
                ->limit(250)
                ->get();
            foreach ($validationVersions as $version) {
                $review = $version->review;
                $code = sprintf(
                    'VAL-CMS-REC-%06d-%03d-V%d',
                    $review->cms_recommendation_case_id,
                    $review->validation_sequence,
                    $version->version_number,
                );
                $options->push($this->option(
                    'CMS',
                    'VALIDATION_VERSION',
                    $version->id,
                    $code,
                    "{$code} — Independent Validation Version",
                ));
                $options->push(...$version->items->map(
                    fn ($item): array => $this->option(
                        'CMS',
                        'VALIDATION_ITEM',
                        $item->id,
                        "CMS-VAL-ITEM-{$item->id}",
                        "{$code} — Validation Item {$item->sequence_number}",
                    ),
                ));
            }
        }

        if ($user->hasPermission('cms.extension-evidence.upload')) {
            $visibleCaseIds = $this->cmsScope
                ->visibleCases(
                    CmsRecommendationCase::query(),
                    $user,
                    'cms.extension.view',
                )
                ->pluck('id');
            $extensionVersions = CmsTargetDateExtensionVersion::query()
                ->where('status_code', 'DRAFT')
                ->whereHas(
                    'request',
                    fn ($query) => $query->whereIn('cms_recommendation_case_id', $visibleCaseIds),
                )
                ->with('request')
                ->orderByDesc('id')
                ->limit(250)
                ->get();
            foreach ($extensionVersions as $version) {
                $code = $version->displayCode();
                $options->push($this->option(
                    'CMS',
                    'EXTENSION_REQUEST_VERSION',
                    $version->id,
                    $code,
                    "{$code} — Target-Date Extension Request",
                ));
            }
        }

        if ($user->hasPermission('cms.escalation-evidence.upload')) {
            $visibleCaseIds = $this->cmsScope->visibleCases(CmsRecommendationCase::query(), $user, 'cms.escalation.view')->pluck('id');
            $noticeVersions = CmsEscalationNoticeVersion::query()->where('status_code', 'DRAFT')->whereHas('escalation', fn ($query) => $query->whereHas('case', fn ($case) => $case->whereIn('id', $visibleCaseIds)))->with('escalation')->orderByDesc('id')->limit(250)->get();
            foreach ($noticeVersions as $version) {
                $code = "ESC-NOTICE-{$version->cms_escalation_id}-V{$version->version_number}";
                $options->push($this->option('CMS', 'ESCALATION_NOTICE_VERSION', $version->id, $code, "{$code} — Escalation Notice"));
            }
            $responseVersions = CmsEscalationResponseVersion::query()->where('status_code', 'DRAFT')->whereHas('response', fn ($query) => $query->whereHas('escalation', fn ($escalation) => $escalation->whereHas('case', fn ($case) => $case->whereIn('id', $visibleCaseIds))))->with('response')->orderByDesc('id')->limit(250)->get();
            foreach ($responseVersions as $version) {
                $code = "ESC-RESPONSE-{$version->cms_escalation_response_id}-V{$version->version_number}";
                $options->push($this->option('CMS', 'ESCALATION_RESPONSE_VERSION', $version->id, $code, "{$code} — Escalation Response"));
            }
        }

        return $options
            ->sortBy(fn (array $option): string => "{$option['module']}|{$option['recordType']}|{$option['label']}")
            ->values();
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array<string, mixed>>
     */
    public function resolve(User $user, array $links): array
    {
        $available = $this->options($user)->keyBy('key');
        $resolved = collect($links)->map(function (array $link) use ($available, $user): array {
            $key = $this->key(
                strtoupper((string) ($link['module'] ?? '')),
                strtoupper((string) ($link['recordType'] ?? '')),
                (int) ($link['recordId'] ?? 0),
            );
            $option = $available->get($key);

            if (! $option) {
                throw ValidationException::withMessages([
                    'links' => ['One or more module links are unavailable or outside your access scope.'],
                ]);
            }

            return [
                'module_code' => $option['module'],
                'record_type' => $option['recordType'],
                'record_id' => $option['recordId'],
                'record_code' => $option['recordCode'],
                'record_label' => $option['label'],
                'linked_by' => $user->id,
            ];
        });

        if ($resolved->map(
            fn (array $link): string => $this->key(
                $link['module_code'],
                $link['record_type'],
                $link['record_id'],
            ),
        )->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'links' => ['Duplicate module links are not allowed.'],
            ]);
        }

        return $resolved->values()->all();
    }

    /** @return array<string, mixed> */
    private function option(
        string $module,
        string $recordType,
        int $recordId,
        ?string $recordCode,
        string $label,
    ): array {
        return [
            'key' => $this->key($module, $recordType, $recordId),
            'module' => $module,
            'moduleLabel' => self::MODULES[$module],
            'recordType' => $recordType,
            'recordId' => $recordId,
            'recordCode' => $recordCode,
            'label' => $label,
        ];
    }

    private function key(string $module, string $recordType, int $recordId): string
    {
        return "{$module}:{$recordType}:{$recordId}";
    }
}
