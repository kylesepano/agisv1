<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementPlan;
use App\Models\AuditEngagementPlanVersion;
use App\Models\EngagementEvent;
use App\Models\MasterList;
use App\Models\MasterListItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Controls the Audit Engagement Plan lifecycle. AEP content is append-only:
 * edits and formal revisions create immutable versions, while the plan family
 * carries the current workflow state and optimistic lock.
 */
class AemsAepService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(AuditEngagement $engagement): array
    {
        $engagement->loadMissing([
            'engagementOrder',
            'engagementPlan.versions.creator',
            'engagementPlan.versions.confidentialityLevel',
            'engagementPlan.preparer',
            'engagementPlan.submitter',
            'engagementPlan.approver',
            'teamMembers' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->with('user'),
        ]);
        $plan = $engagement->engagementPlan;

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'objectives' => $engagement->objectives,
                'scope' => $engagement->scope,
                'exclusions' => $engagement->exclusions,
                'plannedStartDate' => $engagement->planned_start_date?->toDateString(),
                'plannedEndDate' => $engagement->planned_end_date?->toDateString(),
                'expectedReportDate' => $engagement->expected_report_date?->toDateString(),
                'plannedPersonDays' => (float) $engagement->planned_person_days,
                'sourceRiskSnapshot' => data_get($engagement->source_snapshot, 'riskAssessment'),
                'sourceMateriality' => data_get(
                    $engagement->source_snapshot,
                    'auditUniverse.materialityExposure',
                ),
            ],
            'issuedAeo' => $engagement->engagementOrder?->status === 'ISSUED',
            'plan' => $plan ? $this->plan($plan, $engagement) : null,
            'confidentialityLevels' => $this->confidentialityLevels(),
            'team' => $engagement->teamMembers->map(fn ($member): array => [
                'id' => $member->id,
                'role' => $member->assignment_role_code,
                'plannedPersonDays' => (float) $member->planned_person_days,
                'user' => $this->user($member->user),
            ])->values(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): AuditEngagementPlan {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aep.create',
        );

        return DB::transaction(function () use ($request, $engagement, $attributes): AuditEngagementPlan {
            $lockedEngagement = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $this->ensurePlanningPhase($lockedEngagement);
            $this->ensureIssuedAeo($lockedEngagement);
            if ($lockedEngagement->engagementPlan()->exists()) {
                throw ValidationException::withMessages([
                    'plan' => ['This engagement already has an active AEP.'],
                ]);
            }

            $plan = AuditEngagementPlan::query()->create([
                'audit_engagement_id' => $lockedEngagement->id,
                'plan_code' => $this->planCode($lockedEngagement),
                'status' => 'DRAFT',
                'current_version_number' => 1,
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $version = $this->createVersion(
                $request,
                $lockedEngagement,
                $plan,
                $attributes,
                1,
            );
            $this->event(
                $request,
                $lockedEngagement,
                $plan,
                $version,
                'CREATE',
                null,
                'DRAFT',
                null,
                $this->versionSnapshot($version),
            );
            $this->support->audit(
                $request,
                'aems.aep.created',
                $lockedEngagement,
                null,
                $this->versionSnapshot($version),
                ['aepId' => $plan->id, 'aepCode' => $plan->plan_code],
            );

            return $plan->fresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementPlan $plan,
        array $attributes,
    ): AuditEngagementPlan {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aep.create',
        );

        return DB::transaction(function () use ($request, $engagement, $plan, $attributes): AuditEngagementPlan {
            $this->ensurePlanningPhase($engagement);
            $locked = $this->lockPlan($engagement, $plan, (int) $attributes['lockVersion']);
            if (! in_array($locked->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only a draft or returned AEP can be edited. Start a formal revision after approval.'],
                ]);
            }
            $previous = $locked->latestVersion()->firstOrFail();
            $number = $locked->current_version_number + 1;
            $version = $this->createVersion($request, $engagement, $locked, $attributes, $number);
            $before = $this->versionSnapshot($previous);
            $after = $this->versionSnapshot($version);

            $locked->update([
                'current_version_number' => $number,
                'prepared_by' => $request->user()->id,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event(
                $request,
                $engagement,
                $locked,
                $version,
                'UPDATE',
                $locked->status,
                $locked->status,
                $before,
                $after,
                $attributes['changeReason'] ?? null,
            );
            $this->support->audit(
                $request,
                'aems.aep.version_created',
                $engagement,
                $before,
                $after,
                ['aepId' => $locked->id, 'aepCode' => $locked->plan_code],
            );

            return $locked->fresh();
        });
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementPlan $plan,
        string $action,
        int $lockVersion,
        ?string $comment,
    ): AuditEngagementPlan {
        $permissions = [
            'SUBMIT' => 'aems.aep.create',
            'RESUBMIT' => 'aems.aep.create',
            'REVIEW' => 'aems.aep.review',
            'RETURN' => 'aems.aep.review',
            'APPROVE' => 'aems.aep.approve',
        ];
        if (! isset($permissions[$action])) {
            throw ValidationException::withMessages(['action' => ['Unsupported AEP workflow action.']]);
        }
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permissions[$action],
            in_array($action, ['REVIEW', 'RETURN', 'APPROVE'], true)
                ? $plan->prepared_by : null,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $plan,
            $action,
            $lockVersion,
            $comment,
        ): AuditEngagementPlan {
            $this->ensurePlanningPhase($engagement);
            $locked = $this->lockPlan($engagement, $plan, $lockVersion);
            $version = $locked->latestVersion()->firstOrFail();
            $from = $locked->status;
            $to = $this->nextStatus($locked, $action);
            if ($action === 'RETURN' && mb_strlen(trim((string) $comment)) < 5) {
                throw ValidationException::withMessages([
                    'comment' => ['A clear return instruction is required.'],
                ]);
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $this->ensureIssuedAeo($engagement);
                $this->ensureComplete($version);
            }
            if ($action === 'APPROVE') {
                $mayUseSingleCiasAuthority = $this->access->mayUseSingleCiasHeadReviewException(
                    $request->user(),
                    'aems.aep.approve',
                );
                $reviewed = EngagementEvent::query()
                    ->where('audit_engagement_id', $engagement->id)
                    ->where('subject_type', 'AEP')
                    ->where('subject_id', $locked->id)
                    ->where('subject_version', $locked->current_version_number)
                    ->where('action', 'AEP_REVIEW')
                    ->when(
                        ! $mayUseSingleCiasAuthority,
                        fn ($query) => $query->where('actor_id', '<>', $locked->prepared_by),
                    )
                    ->exists();
                if (! $reviewed) {
                    throw ValidationException::withMessages([
                        'action' => ['The current AEP version must be independently reviewed before approval.'],
                    ]);
                }
            }

            $changes = ['lock_version' => $locked->lock_version + 1];
            if ($action !== 'REVIEW') {
                $changes['status'] = $to;
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $changes['submitted_by'] = $request->user()->id;
                $changes['submitted_at'] = now();
            }
            if ($action === 'APPROVE') {
                $changes['approved_by'] = $request->user()->id;
                $changes['approved_at'] = now();
            }
            $locked->update($changes);
            $this->event(
                $request,
                $engagement,
                $locked,
                $version,
                $action,
                $from,
                $to,
                ['status' => $from],
                ['status' => $to, 'versionNumber' => $version->version_number],
                $comment,
            );
            $this->support->audit(
                $request,
                'aems.aep.'.str($action)->lower(),
                $engagement,
                ['status' => $from],
                ['status' => $to, 'versionNumber' => $version->version_number],
                ['aepId' => $locked->id, 'aepCode' => $locked->plan_code, 'comment' => $comment],
            );
            $this->notifications->controlledDocumentTransition(
                $request,
                $engagement,
                'AEP',
                $locked->id,
                $locked->plan_code,
                'Engagement Plan',
                $action,
                $version->version_number,
                $locked->prepared_by,
                $locked->submitted_by,
                'aems.aep.review',
                "/audit-engagement-management/aep?engagementId={$engagement->id}",
            );

            return $locked->fresh();
        });
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementPlan $plan,
        int $lockVersion,
        string $reason,
    ): AuditEngagementPlan {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.aep.revise',
            $plan->prepared_by,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $plan,
            $lockVersion,
            $reason,
        ): AuditEngagementPlan {
            $this->ensurePlanningPhase($engagement);
            $locked = $this->lockPlan($engagement, $plan, $lockVersion);
            if ($locked->status !== 'APPROVED') {
                throw ValidationException::withMessages([
                    'status' => ['Only an approved AEP can start a formal revision.'],
                ]);
            }
            $source = $locked->latestVersion()->firstOrFail();
            $number = $locked->current_version_number + 1;
            $version = AuditEngagementPlanVersion::query()->create([
                ...$source->only([
                    'objectives',
                    'scope',
                    'exclusions',
                    'methodology',
                    'audit_criteria',
                    'materiality',
                    'sampling_approach',
                    'planned_start_date',
                    'planned_end_date',
                    'expected_report_date',
                    'planned_person_days',
                    'resource_requirements',
                    'management_coordination',
                    'linked_risk_snapshot',
                    'confidentiality_level_id',
                ]),
                'audit_engagement_plan_id' => $locked->id,
                'version_number' => $number,
                'change_reason' => $reason,
                'created_by' => $request->user()->id,
            ]);
            $locked->update([
                'status' => 'DRAFT',
                'current_version_number' => $number,
                'prepared_by' => $request->user()->id,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event(
                $request,
                $engagement,
                $locked,
                $version,
                'REVISE',
                'APPROVED',
                'DRAFT',
                ['status' => 'APPROVED', 'versionNumber' => $source->version_number],
                ['status' => 'DRAFT', 'versionNumber' => $number],
                $reason,
            );
            $this->support->audit(
                $request,
                'aems.aep.revision_started',
                $engagement,
                ['status' => 'APPROVED', 'versionNumber' => $source->version_number],
                ['status' => 'DRAFT', 'versionNumber' => $number],
                ['aepId' => $locked->id, 'aepCode' => $locked->plan_code, 'reason' => $reason],
            );

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    private function createVersion(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementPlan $plan,
        array $attributes,
        int $number,
    ): AuditEngagementPlanVersion {
        return AuditEngagementPlanVersion::query()->create([
            'audit_engagement_plan_id' => $plan->id,
            'version_number' => $number,
            'objectives' => $attributes['objectives'],
            'scope' => $attributes['scope'],
            'exclusions' => $attributes['exclusions'] ?? null,
            'methodology' => $attributes['methodology'],
            'audit_criteria' => $attributes['auditCriteria'],
            'materiality' => $attributes['materiality'] ?? null,
            'sampling_approach' => $attributes['samplingApproach'] ?? null,
            'planned_start_date' => $attributes['plannedStartDate'],
            'planned_end_date' => $attributes['plannedEndDate'],
            'expected_report_date' => $attributes['expectedReportDate'] ?? null,
            'planned_person_days' => $attributes['plannedPersonDays'],
            'resource_requirements' => $attributes['resourceRequirements'] ?? [],
            'management_coordination' => $attributes['managementCoordination'] ?? [],
            'linked_risk_snapshot' => [
                'capturedAt' => now()->toISOString(),
                'sourceType' => $engagement->source_type,
                'riskAssessment' => data_get($engagement->source_snapshot, 'riskAssessment'),
                'prioritization' => data_get($engagement->source_snapshot, 'prioritization'),
                'auditUniverse' => data_get($engagement->source_snapshot, 'auditUniverse'),
            ],
            'confidentiality_level_id' => $attributes['confidentialityLevelId'] ?? null,
            'change_reason' => $attributes['changeReason'] ?? null,
            'created_by' => $request->user()->id,
        ]);
    }

    private function lockPlan(
        AuditEngagement $engagement,
        AuditEngagementPlan $plan,
        int $lockVersion,
    ): AuditEngagementPlan {
        $locked = AuditEngagementPlan::query()->lockForUpdate()->findOrFail($plan->id);
        if ((int) $locked->audit_engagement_id !== (int) $engagement->id || $locked->trashed()) {
            throw ValidationException::withMessages(['plan' => ['The AEP does not belong to this engagement.']]);
        }
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This AEP changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function ensureIssuedAeo(AuditEngagement $engagement): void
    {
        if (! $engagement->engagementOrder()->where('status', 'ISSUED')->exists()) {
            throw ValidationException::withMessages([
                'engagement' => ['The Audit Engagement Order must be issued before preparing or submitting the AEP.'],
            ]);
        }
    }

    private function ensurePlanningPhase(AuditEngagement $engagement): void
    {
        if ($engagement->status !== 'ENGAGEMENT_PLANNING') {
            throw ValidationException::withMessages([
                'engagement' => ['Planning Workspace is locked until the engagement reaches ENGAGEMENT_PLANNING after the issued AEO is acknowledged by the auditee office.'],
            ]);
        }
    }

    private function ensureComplete(AuditEngagementPlanVersion $version): void
    {
        if (! $version->materiality && ! $version->sampling_approach) {
            throw ValidationException::withMessages([
                'materiality' => ['Enter a materiality basis, a sampling approach, or both before submission.'],
            ]);
        }
        if ($version->planned_person_days <= 0) {
            throw ValidationException::withMessages([
                'plannedPersonDays' => ['Planned person-days must be greater than zero.'],
            ]);
        }
    }

    private function nextStatus(AuditEngagementPlan $plan, string $action): string
    {
        $transitions = [
            'DRAFT' => ['SUBMIT' => 'PENDING_REVIEW'],
            'PENDING_REVIEW' => [
                'REVIEW' => 'PENDING_REVIEW',
                'RETURN' => 'RETURNED_FOR_REVISION',
                'APPROVE' => 'APPROVED',
            ],
            'RETURNED_FOR_REVISION' => ['RESUBMIT' => 'RESUBMITTED'],
            'RESUBMITTED' => [
                'REVIEW' => 'RESUBMITTED',
                'RETURN' => 'RETURNED_FOR_REVISION',
                'APPROVE' => 'APPROVED',
            ],
        ];
        $next = $transitions[$plan->status][$action] ?? null;
        if (! $next) {
            throw ValidationException::withMessages([
                'action' => ["{$action} is not allowed while the AEP is {$plan->status}."],
            ]);
        }

        return $next;
    }

    private function planCode(AuditEngagement $engagement): string
    {
        $base = 'AEP-'.$engagement->engagement_code;
        if (! AuditEngagementPlan::withTrashed()->where('plan_code', $base)->exists()) {
            return $base;
        }

        return $base.'-'.Str::upper(Str::random(4));
    }

    /** @return array<string, mixed> */
    private function plan(AuditEngagementPlan $plan, AuditEngagement $engagement): array
    {
        $events = EngagementEvent::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('subject_type', 'AEP')
            ->where('subject_id', $plan->id)
            ->with('actor')
            ->latest('created_at')
            ->get();
        $versions = $plan->versions->sortByDesc('version_number');

        return [
            'id' => $plan->id,
            'planCode' => $plan->plan_code,
            'status' => $plan->status,
            'currentVersionNumber' => $plan->current_version_number,
            'lockVersion' => $plan->lock_version,
            'preparedBy' => $this->user($plan->preparer),
            'submittedBy' => $this->user($plan->submitter),
            'submittedAt' => $plan->submitted_at?->toISOString(),
            'approvedBy' => $this->user($plan->approver),
            'approvedAt' => $plan->approved_at?->toISOString(),
            'latestVersion' => $plan->latestVersion
                ? $this->versionSnapshot($plan->latestVersion) : null,
            'versions' => $versions->map(
                fn (AuditEngagementPlanVersion $version): array => $this->versionSnapshot($version),
            )->values(),
            'events' => $events->map(fn (EngagementEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'subjectVersion' => $event->subject_version,
                'comment' => $event->comment,
                'createdAt' => $event->created_at?->toISOString(),
                'actor' => $this->user($event->actor),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function versionSnapshot(AuditEngagementPlanVersion $version): array
    {
        return [
            'id' => $version->id,
            'versionNumber' => $version->version_number,
            'objectives' => $version->objectives,
            'scope' => $version->scope,
            'exclusions' => $version->exclusions,
            'methodology' => $version->methodology,
            'auditCriteria' => $version->audit_criteria,
            'materiality' => $version->materiality,
            'samplingApproach' => $version->sampling_approach,
            'plannedStartDate' => $version->planned_start_date?->toDateString(),
            'plannedEndDate' => $version->planned_end_date?->toDateString(),
            'expectedReportDate' => $version->expected_report_date?->toDateString(),
            'plannedPersonDays' => (float) $version->planned_person_days,
            'resourceRequirements' => $version->resource_requirements ?? [],
            'managementCoordination' => $version->management_coordination ?? [],
            'linkedRiskSnapshot' => $version->linked_risk_snapshot ?? [],
            'confidentialityLevelId' => $version->confidentiality_level_id,
            'confidentialityLevel' => $version->confidentialityLevel?->label,
            'changeReason' => $version->change_reason,
            'createdBy' => $this->user($version->creator),
            'createdAt' => $version->created_at?->toISOString(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function confidentialityLevels(): array
    {
        $listId = MasterList::query()->where('code', 'DOCUMENT_CONFIDENTIALITY')->value('id');

        return MasterListItem::query()
            ->where('master_list_id', $listId)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get()
            ->map(fn (MasterListItem $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'label' => $item->label,
            ])->all();
    }

    /** @return array<string, mixed>|null */
    private function user(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function event(
        Request $request,
        AuditEngagement $engagement,
        AuditEngagementPlan $plan,
        AuditEngagementPlanVersion $version,
        string $action,
        ?string $from,
        ?string $to,
        ?array $oldValues,
        ?array $newValues,
        ?string $comment = null,
    ): void {
        $this->support->event(
            $request,
            $engagement,
            'AEP_'.$action,
            $from,
            $to,
            $oldValues,
            $newValues,
            $comment,
            'AEP',
            $plan->id,
            $version->version_number,
            $plan->plan_code,
        );
    }
}
