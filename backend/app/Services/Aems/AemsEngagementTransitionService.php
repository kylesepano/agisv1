<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\EngagementClosure;
use App\Models\EntryConference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative executor for aggregate AEMS engagement status changes.
 *
 * Child services retain ownership of their own states. This service only reads
 * their controlled records as gates and never weakens or bypasses a child
 * workflow.
 */
class AemsEngagementTransitionService
{
    private const NORMAL_TRANSITIONS = [
        'DRAFT' => [
            'PREPARE_AUTHORIZATION' => 'AUTHORIZATION_PREPARATION',
        ],
        'AUTHORIZATION_PREPARATION' => [
            'ISSUE_AUTHORIZATION' => 'AUTHORIZED',
        ],
        'AUTHORIZED' => [
            'START_PLANNING' => 'ENGAGEMENT_PLANNING',
        ],
        'ENGAGEMENT_PLANNING' => [
            'START_ENTRY_CONFERENCE' => 'ENTRY_CONFERENCE',
        ],
        'ENTRY_CONFERENCE' => [
            'START_FIELDWORK' => 'FIELDWORK',
        ],
        'FIELDWORK' => [
            'END_FIELDWORK' => 'FINDINGS_COMMUNICATION',
            'START_FINDINGS_COMMUNICATION' => 'FINDINGS_COMMUNICATION',
        ],
        'FINDINGS_COMMUNICATION' => [
            'START_EXIT_CONFERENCE' => 'EXIT_CONFERENCE',
        ],
        'EXIT_CONFERENCE' => [
            'START_REPORTING' => 'REPORTING',
        ],
        'REPORTING' => [
            'ISSUE_FINAL_REPORT' => 'ISSUED',
        ],
        'ISSUED' => [
            'SUBMIT_FOR_CLOSURE' => 'CLOSURE_REVIEW',
        ],
        'CLOSURE_REVIEW' => [
            'COMPLETE_AUDIT_WORK' => 'COMPLETED',
        ],
        'COMPLETED' => [
            'OPEN_CLOSURE_REVIEW' => 'CLOSURE_REVIEW',
        ],
    ];

    private const RETURNABLE_STATES = [
        'AUTHORIZATION_PREPARATION',
        'ENGAGEMENT_PLANNING',
        'REPORTING',
        'EXIT_CONFERENCE',
        'CLOSURE_REVIEW',
    ];

    private const SUSPENDABLE_STATES = [
        'AUTHORIZATION_PREPARATION',
        'AUTHORIZED',
        'ENGAGEMENT_PLANNING',
        'ENTRY_CONFERENCE',
        'FIELDWORK',
        'FINDINGS_COMMUNICATION',
        'EXIT_CONFERENCE',
        'REPORTING',
    ];

    private const CANCELLABLE_STATES = [
        'DRAFT',
        'AUTHORIZATION_PREPARATION',
        'RETURNED_FOR_REVISION',
        'AUTHORIZED',
        'ENGAGEMENT_PLANNING',
        'ENTRY_CONFERENCE',
        'FIELDWORK',
        'FINDINGS_COMMUNICATION',
        'EXIT_CONFERENCE',
        'REPORTING',
    ];

    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
        private readonly AemsTeamSafeguardService $teamSafeguards,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(User $user, AuditEngagement $engagement): array
    {
        $engagement = $this->load($engagement);
        $actions = collect($this->candidateActions($engagement))
            ->filter(fn (string $action): bool => $this->canPerform($user, $engagement, $action))
            ->map(function (string $action) use ($engagement): array {
                $requirements = $this->requirements($engagement, $action);

                return [
                    'action' => $action,
                    'label' => str($action)->replace('_', ' ')->title()->toString(),
                    'targetStatus' => $this->targetStatus($engagement, $action),
                    'requiresComment' => in_array(
                        $action,
                        ['RETURN', 'RESUBMIT', 'SUSPEND', 'RESUME', 'CANCEL', 'SUBMIT_FOR_CLOSURE', 'COMPLETE_AUDIT_WORK', 'OPEN_CLOSURE_REVIEW'],
                        true,
                    ),
                    'requirements' => $requirements,
                    'blockers' => collect($requirements)->where('met', false)->pluck('label')->values()->all(),
                    'canExecute' => collect($requirements)->every('met', true)
                        && $action !== 'APPROVE_CLOSURE',
                ];
            })
            ->values()
            ->all();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'phase' => $engagement->phase,
                'administrativeStatus' => $engagement->administrative_status,
                'lockVersion' => $engagement->lock_version,
                'returnedFromStatus' => $engagement->returned_from_status,
                'returnToStatus' => $engagement->return_to_status,
                'suspendedFromStatus' => $engagement->suspended_from_status,
                'statusReason' => $engagement->status_reason,
                'suspensionMetadata' => $engagement->suspension_metadata,
                'cancellationMetadata' => $engagement->cancellation_metadata,
                'isArchived' => $engagement->trashed(),
            ],
            'states' => AuditEngagement::STATUSES,
            'actions' => $actions,
            'timeline' => $engagement->events
                ->where('subject_type', 'ENGAGEMENT')
                ->map(fn ($event): array => [
                    'id' => $event->id,
                    'action' => $event->action,
                    'fromStatus' => $event->from_status,
                    'toStatus' => $event->to_status,
                    'comment' => $event->comment,
                    'createdAt' => $event->created_at?->toISOString(),
                    'actor' => $event->actor ? [
                        'id' => $event->actor->id,
                        'name' => $event->actor->name,
                    ] : null,
                ])->values()->all(),
            'relatedLinks' => $this->relatedLinks($engagement),
            'closureDeferred' => false,
        ];
    }

    /** @param array<string, mixed> $details */
    public function transition(
        Request $request,
        AuditEngagement $engagement,
        string $action,
        int $lockVersion,
        array $details = [],
    ): AuditEngagement {
        $action = strtoupper($action);
        if ($action === 'APPROVE_CLOSURE') {
            throw ValidationException::withMessages([
                'action' => ['Use the formal Closure workflow to approve and close the engagement.'],
            ]);
        }
        $this->authorizeAction($request->user(), $engagement, $action);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $action,
            $lockVersion,
            $details,
        ): AuditEngagement {
            $locked = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            if ($locked->trashed() || ! $locked->is_active) {
                throw ValidationException::withMessages([
                    'engagement' => ['Archived or inactive engagements cannot transition.'],
                ]);
            }
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages([
                    'lockVersion' => ['This engagement changed in another session. Refresh before continuing.'],
                ]);
            }
            if (in_array($locked->status, ['CLOSED', 'CANCELLED'], true)) {
                throw ValidationException::withMessages([
                    'action' => ["{$locked->status} is terminal and cannot transition."],
                ]);
            }

            $from = $locked->status;
            $to = $this->targetStatus($locked, $action);
            $this->validateComment($action, $details);
            $requirements = $this->requirements($this->load($locked), $action);
            $blockers = collect($requirements)->where('met', false)->pluck('label')->values();
            if ($blockers->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'requirements' => $blockers->all(),
                ]);
            }

            $before = $this->snapshot($locked);
            $projection = AuditEngagement::lifecycleProjectionForStatus(
                $to,
                $to === 'SUSPENDED' ? $from : $locked->suspended_from_status,
            );
            $changes = [
                'status' => $to,
                ...$projection,
                'status_reason' => $details['comment'] ?? null,
                'transitioned_by' => $request->user()->id,
                'transitioned_at' => now(),
                'updated_by' => $request->user()->id,
                'lock_version' => $locked->lock_version + 1,
            ];

            if ($action === 'SUSPEND') {
                $changes['suspended_from_status'] = $from;
                $changes['suspension_metadata'] = [
                    'reason' => $details['comment'],
                    'authority' => $details['authority'] ?? null,
                    'effectiveDate' => $details['effectiveDate'] ?? now()->toDateString(),
                    'expectedReviewDate' => $details['expectedReviewDate'] ?? null,
                    'resumeRequirements' => $details['resumeRequirements'] ?? null,
                    'suspendedBy' => $request->user()->id,
                    'suspendedAt' => now()->toISOString(),
                ];
            } elseif ($action === 'RESUME') {
                $changes['suspended_from_status'] = null;
                $changes['suspension_metadata'] = [
                    ...($locked->suspension_metadata ?? []),
                    'resumeComment' => $details['comment'],
                    'resumedBy' => $request->user()->id,
                    'resumedAt' => now()->toISOString(),
                ];
            } elseif ($action === 'CANCEL') {
                $changes['is_active'] = false;
                $changes['cancelled_by'] = $request->user()->id;
                $changes['cancelled_at'] = now();
                $changes['cancellation_metadata'] = [
                    'authority' => $details['authority'] ?? null,
                    'reason' => $details['comment'],
                    'iapEffect' => $details['effectOnIap'],
                    'workProductDisposition' => $details['workProductDisposition'] ?? null,
                    'notificationRecipients' => $details['notificationRecipients'] ?? [],
                    'cancelledBy' => $request->user()->id,
                    'cancelledAt' => now()->toISOString(),
                ];
            } elseif ($action === 'RETURN') {
                $changes['returned_from_status'] = $from;
                $changes['return_to_status'] = $from;
            } elseif ($action === 'RESUBMIT') {
                $changes['returned_from_status'] = null;
                $changes['return_to_status'] = null;
            }

            if ($to === 'FIELDWORK' && ! $locked->actual_start_date) {
                $changes['actual_start_date'] = today();
            }
            if ($to === 'COMPLETED') {
                $changes['actual_end_date'] = $locked->actual_end_date ?? today();
            }

            $locked->forceFill($changes)->save();
            $after = $this->snapshot($locked);
            $this->support->event(
                $request,
                $locked,
                $action,
                $from,
                $to,
                $before,
                $after,
                $details['comment'] ?? null,
            );
            $this->support->audit(
                $request,
                'aems.engagement.transition.'.strtolower($action),
                $locked,
                $before,
                $after,
                ['requirements' => $requirements],
            );
            $this->notifications->engagementTransition($request, $locked, $action, $from, $to);

            return $this->load($locked);
        });
    }

    /**
     * Called by the controlled Final Report issuance transaction so the report
     * service never writes the aggregate status directly.
     */
    public function synchronizeIssuedReport(
        Request $request,
        AuditEngagement $engagement,
    ): AuditEngagement {
        $current = AuditEngagement::query()->findOrFail($engagement->id);
        if ($current->status === 'ISSUED') {
            return $current;
        }

        return $this->transition(
            $request,
            $current,
            'ISSUE_FINAL_REPORT',
            $current->lock_version,
            ['comment' => 'Aggregate lifecycle synchronized from controlled Final Report issuance.'],
        );
    }

    /**
     * Executes the sole final CLOSED transition after the formal Closure
     * aggregate has been approved. The guard evaluator is invoked inside the
     * same transaction after engagement and closure row locks are acquired.
     *
     * @param  callable(AuditEngagement): list<array<string, mixed>>  $guardEvaluator
     */
    public function closeApprovedClosure(
        Request $request,
        AuditEngagement $engagement,
        EngagementClosure $closure,
        int $engagementLockVersion,
        int $closureLockVersion,
        callable $guardEvaluator,
    ): EngagementClosure {
        Gate::forUser($request->user())->authorize('close', $engagement);

        return DB::transaction(function () use (
            $request,
            $engagement,
            $closure,
            $engagementLockVersion,
            $closureLockVersion,
            $guardEvaluator,
        ): EngagementClosure {
            $lockedEngagement = AuditEngagement::query()
                ->withTrashed()
                ->lockForUpdate()
                ->findOrFail($engagement->id);
            $lockedClosure = EngagementClosure::query()
                ->lockForUpdate()
                ->findOrFail($closure->id);
            if ($lockedEngagement->lock_version !== $engagementLockVersion) {
                throw ValidationException::withMessages([
                    'engagementLockVersion' => ['The engagement changed in another session. Refresh first.'],
                ]);
            }
            if ($lockedClosure->lock_version !== $closureLockVersion) {
                throw ValidationException::withMessages([
                    'lockVersion' => ['The Closure record changed in another session. Refresh first.'],
                ]);
            }
            if ($lockedEngagement->trashed() || ! $lockedEngagement->is_active
                || $lockedEngagement->status !== 'CLOSURE_REVIEW') {
                throw ValidationException::withMessages([
                    'engagement' => ['Only an active engagement in CLOSURE_REVIEW can be closed.'],
                ]);
            }
            if ((int) $lockedClosure->audit_engagement_id !== (int) $lockedEngagement->id
                || ! $lockedClosure->is_current_revision
                || $lockedClosure->status_code !== 'APPROVED') {
                throw ValidationException::withMessages([
                    'closure' => ['The current formal Closure record must be approved.'],
                ]);
            }
            $assessment = $lockedEngagement->currentCompletionAssessment()->first();
            if (! $assessment
                || $assessment->status_code !== 'APPROVED'
                || (int) $lockedClosure->completion_assessment_id !== (int) $assessment->id) {
                throw ValidationException::withMessages([
                    'completionAssessment' => ['The Closure must reference the current approved Completion Assessment.'],
                ]);
            }
            $guards = $guardEvaluator($lockedEngagement);
            $blockers = collect($guards)->filter(
                fn (array $guard): bool => ($guard['blockingFlag'] ?? false)
                    && ! in_array($guard['resultCode'] ?? null, ['PASS', 'NOT_APPLICABLE'], true),
            )->pluck('description')->values();
            if ($blockers->isNotEmpty()) {
                throw ValidationException::withMessages(['checklist' => $blockers->all()]);
            }
            if (! $lockedClosure->closure_document_version_id) {
                throw ValidationException::withMessages([
                    'closure' => ['The approved Closure snapshot DocumentVersion is missing.'],
                ]);
            }

            $closedAt = now();
            $engagementBefore = $this->snapshot($lockedEngagement);
            $closureBefore = [
                'statusCode' => $lockedClosure->status_code,
                'lockVersion' => $lockedClosure->lock_version,
                'documentIndexLockedAt' => $lockedClosure->document_index_locked_at?->toISOString(),
            ];
            $closureSnapshot = [
                'closureCode' => $lockedClosure->closure_code,
                'revisionNumber' => $lockedClosure->revision_number,
                'completionAssessmentId' => $assessment->id,
                'closureDocumentVersionId' => $lockedClosure->closure_document_version_id,
                'checklist' => $guards,
                'closedBy' => $request->user()->id,
                'closedAt' => $closedAt->toISOString(),
            ];
            $lockedClosure->forceFill([
                'status_code' => 'CLOSED',
                'closed_by' => $request->user()->id,
                'closed_at' => $closedAt,
                'document_index_locked_at' => $closedAt,
                'document_index_locked_by' => $request->user()->id,
                'closed_snapshot_json' => $closureSnapshot,
                'lock_version' => $lockedClosure->lock_version + 1,
            ])->save();
            $lockedEngagement->forceFill([
                'status' => 'CLOSED',
                ...AuditEngagement::lifecycleProjectionForStatus('CLOSED'),
                'status_reason' => 'Formal Completion Assessment and Engagement Closure approved.',
                'actual_end_date' => $lockedEngagement->actual_end_date ?? today(),
                'closed_by' => $request->user()->id,
                'closed_at' => $closedAt,
                'transitioned_by' => $request->user()->id,
                'transitioned_at' => $closedAt,
                'updated_by' => $request->user()->id,
                'lock_version' => $lockedEngagement->lock_version + 1,
            ])->save();
            $closureAfter = [
                'statusCode' => 'CLOSED',
                'lockVersion' => $lockedClosure->lock_version,
                'documentIndexLockedAt' => $closedAt->toISOString(),
                'closedSnapshot' => $closureSnapshot,
            ];
            $lockedClosure->events()->create([
                'audit_engagement_id' => $lockedEngagement->id,
                'action_code' => 'CLOSE_ENGAGEMENT',
                'from_status' => 'APPROVED',
                'to_status' => 'CLOSED',
                'actor_id' => $request->user()->id,
                'comment' => 'All authoritative closure requirements re-evaluated and passed.',
                'snapshot_json' => $closureSnapshot,
                'occurred_at' => $closedAt,
                'request_metadata_json' => [
                    'ipAddress' => $request->ip(),
                    'userAgent' => mb_substr((string) $request->userAgent(), 0, 1000),
                ],
            ]);
            $this->support->event(
                $request,
                $lockedEngagement,
                'CLOSE_ENGAGEMENT',
                'CLOSURE_REVIEW',
                'CLOSED',
                $engagementBefore,
                $this->snapshot($lockedEngagement),
                'Formal Closure approved and all authoritative guards passed.',
                'ENGAGEMENT',
                $lockedEngagement->id,
                $lockedEngagement->reopen_revision_number,
                $lockedEngagement->engagement_code,
                null,
                [$lockedClosure->closure_document_version_id],
            );
            $this->support->audit(
                $request,
                'aems.engagement.closed',
                $lockedEngagement,
                $engagementBefore,
                $this->snapshot($lockedEngagement),
                [
                    'closureId' => $lockedClosure->id,
                    'completionAssessmentId' => $assessment->id,
                    'closureBefore' => $closureBefore,
                    'closureAfter' => $closureAfter,
                    'documentVersionIds' => [$lockedClosure->closure_document_version_id],
                ],
            );
            $this->notifications->closure(
                $request,
                $lockedEngagement,
                $lockedClosure,
                'CLOSE_ENGAGEMENT',
            );
            $this->notifications->engagementTransition(
                $request,
                $lockedEngagement,
                'CLOSE_ENGAGEMENT',
                'CLOSURE_REVIEW',
                'CLOSED',
            );

            return $lockedClosure->fresh([
                'completionAssessment',
                'checklistItems',
                'events',
                'retentionRecord',
                'lessonsLearned',
                'closureDocumentVersion',
            ]);
        });
    }

    /** @return list<array{key: string, label: string, met: bool, link?: string}> */
    public function requirements(AuditEngagement $engagement, string $action): array
    {
        $action = strtoupper($action);
        $engagement = $this->load($engagement);
        $teamRoles = $engagement->teamMembers
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->pluck('assignment_role_code');
        $requiredRoles = collect(['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER']);
        $order = $engagement->engagementOrder;
        $approvedAeo = $order?->is_active && in_array($order->status, ['APPROVED', 'ISSUED'], true);
        $issuedAeo = $approvedAeo && $order->status === 'ISSUED';
        $auditeeOfficeIds = $engagement->engagement_office_id
            ? collect([(int) $engagement->engagement_office_id])
            : $engagement->offices->pluck('id')->map(fn ($id): int => (int) $id);
        $acknowledgedAeo = $issuedAeo
            && $auditeeOfficeIds->isNotEmpty()
            && $engagement->engagementOrder?->distributions()
                ->where('status', 'ACKNOWLEDGED')
                ->when((int) $order->current_version_number > 0, fn ($query) => $query
                    ->where('version_number', $order->current_version_number))
                ->where(function ($query) use ($auditeeOfficeIds): void {
                    $query
                        ->whereIn('recipient_office_id', $auditeeOfficeIds->all())
                        ->orWhereHas('recipient', fn ($recipient) => $recipient->whereIn('office_id', $auditeeOfficeIds->all()));
                })
                ->exists();
        $approvedAep = $engagement->engagementPlan?->status === 'APPROVED';
        $currentProgram = $engagement->programs
            ->where('is_current_revision', true)
            ->where('is_active', true)
            ->sortByDesc('revision_number')
            ->first();
        $approvedProgram = $currentProgram
            && in_array($currentProgram->status, ['APPROVED', 'ACTIVE', 'COMPLETED'], true);
        $planningPackage = $engagement->planningPackage;
        $approvedPlanningPackage = $planningPackage
            && $planningPackage->status === 'APPROVED'
            && (int) $planningPackage->current_version_number === (int) $planningPackage->approved_version_number;
        $planningConformance = $planningPackage?->latestVersion
            ? app(AemsPlanningPackageService::class)->readiness($engagement, $planningPackage, $planningPackage->latestVersion, $currentProgram?->procedures ?? collect())
            : ['fieldworkReady' => false];
        $teamSafeguardGates = $this->teamSafeguards->aggregateGate($engagement);

        return match ($action) {
            'PREPARE_AUTHORIZATION' => [
                $this->gate('source', 'Valid IAP or special-authority source', $this->validSource($engagement)),
                $this->gate('office', 'At least one auditee office is linked', $engagement->offices->isNotEmpty()),
                $this->gate('area', 'At least one audit area is linked', $engagement->auditAreas->isNotEmpty()),
            ],
            'ISSUE_AUTHORIZATION' => [
                $this->gate('approvedAeo', 'Current AEO is approved', $approvedAeo, 'aeo'),
                // AEO approval owns its review controls. Issuance and office
                // acknowledgement are prerequisites for planning, not authorization.
            ],
            'START_PLANNING' => [
                $this->gate('issuedAeo', 'Issued AEO exists', $issuedAeo, 'aeo'),
                $this->gate('acknowledgedAeo', 'Auditee office has acknowledged the issued AEO', $acknowledgedAeo, 'aeo'),
                $this->gate('active', 'Engagement is active and available', $engagement->is_active && ! $engagement->trashed()),
            ],
            'START_ENTRY_CONFERENCE' => [
                $this->gate('approvedAep', 'Current AEP is approved', $approvedAep, 'aep'),
                $this->gate('approvedProgram', 'Current Audit Program is approved', (bool) $approvedProgram, 'audit-program'),
                $this->gate('participants', 'Audit team and auditee offices can be identified', $teamRoles->isNotEmpty() && $engagement->offices->isNotEmpty()),
                $this->planningControlGate(
                    $engagement,
                    'kpis',
                    'Engagement KPI targets and measurement method are defined',
                    'planning-package',
                ),
            ],
            'START_FIELDWORK' => [
                $this->gate(
                    'entryConference',
                    'Entry Conference is completed or formally waived',
                    in_array($engagement->entryConference?->status, EntryConference::TERMINAL_STATUSES, true),
                ),
                $this->gate('approvedAep', 'Current AEP remains approved', $approvedAep, 'aep'),
                $this->gate('approvedProgram', 'Current Audit Program remains approved', (bool) $approvedProgram, 'audit-program'),
                $this->gate('planningPackage', 'Approved Planning Package is required before fieldwork', (bool) $approvedPlanningPackage, 'planning-package'),
                $this->gate('planningConformance', 'Planning Package conformance is complete before fieldwork', (bool) ($approvedPlanningPackage && ($planningConformance['fieldworkReady'] ?? false)), 'planning-package'),
                $this->gate('teamRoles', 'Required team roles remain active', $requiredRoles->diff($teamRoles)->isEmpty(), 'team'),
                ...$teamSafeguardGates,
            ],
            'END_FIELDWORK', 'START_FINDINGS_COMMUNICATION' => [
                $this->gate(
                    'procedures',
                    'Required procedures are completed or formally waived',
                    $currentProgram
                        && $currentProgram->procedures->isNotEmpty()
                        && $currentProgram->procedures->every(
                            fn ($procedure) => in_array($procedure->status, ['COMPLETED', 'WAIVED'], true),
                        ),
                    'audit-program',
                ),
                $this->gate(
                    'workingPapers',
                    'Working Papers are in valid terminal review states',
                    $engagement->workingPapers->isNotEmpty()
                        && $engagement->workingPapers->every(
                            fn ($paper) => in_array($paper->status, ['APPROVED', 'SUPERSEDED', 'VOIDED'], true),
                        ),
                    'working-papers',
                ),
                $this->gate('blockers', 'No undisclosed fieldwork blocker remains', true),
            ],
            'START_EXIT_CONFERENCE' => [
                $this->gate(
                    'issues',
                    'All issues are dismissed or converted to findings',
                    $engagement->issues->every(
                        fn ($issue) => in_array($issue->status, ['DISMISSED', 'CONVERTED_TO_FINDING'], true),
                    ),
                    'issues',
                ),
                $this->gate(
                    'dialogue',
                    'All current findings have finalized dialogue disposition',
                    $engagement->findings
                        ->where('is_current_revision', true)
                        ->every('status', 'FINALIZED'),
                    'findings',
                ),
            ],
            'START_REPORTING' => [
                $this->gate(
                    'exitConference',
                    'Exit Conference is completed or formally waived',
                    $engagement->exitConferences->contains(
                        fn ($conference) => in_array($conference->status, ['COMPLETED', 'WAIVED'], true),
                    ),
                    'conferences',
                ),
                $this->gate(
                    'issues',
                    'All issues are dismissed or converted to findings',
                    $engagement->issues->every(
                        fn ($issue) => in_array($issue->status, ['DISMISSED', 'CONVERTED_TO_FINDING'], true),
                    ),
                    'issues',
                ),
                $this->gate(
                    'dialogue',
                    'All current findings have finalized dialogue disposition',
                    $engagement->findings
                        ->where('is_current_revision', true)
                        ->every('status', 'FINALIZED'),
                    'findings',
                ),
                $this->planningControlGate(
                    $engagement,
                    'progressAssessment',
                    'Engagement progress assessment is recorded or formally not applicable',
                    'planning-package',
                ),
            ],
            'ISSUE_FINAL_REPORT' => $this->finalReportRequirements($engagement),
            'SUBMIT_FOR_CLOSURE' => $this->closureRequirements($engagement),
            'COMPLETE_AUDIT_WORK' => [
                ...$this->closureRequirements($engagement),
                $this->gate('status', 'Engagement is in Closure Review before substantive completion', $engagement->status === 'CLOSURE_REVIEW'),
            ],
            'OPEN_CLOSURE_REVIEW' => [
                $this->gate('status', 'Engagement is substantively completed', $engagement->status === 'COMPLETED'),
            ],
            'SUSPEND' => [
                $this->gate('state', 'Current state permits suspension', in_array($engagement->status, self::SUSPENDABLE_STATES, true)),
            ],
            'RESUME' => [
                $this->gate('priorState', 'A valid suspended prior state is recorded', in_array($engagement->suspended_from_status, self::SUSPENDABLE_STATES, true)),
            ],
            'CANCEL' => [
                $this->gate('state', 'Current state permits cancellation', in_array($engagement->status, self::CANCELLABLE_STATES, true)),
            ],
            'RETURN' => [
                $this->gate('state', 'Current stage supports return for revision', in_array($engagement->status, self::RETURNABLE_STATES, true)),
            ],
            'RESUBMIT' => [
                $this->gate('returnState', 'A valid return destination is recorded', $engagement->status === 'RETURNED_FOR_REVISION'
                    && in_array($engagement->return_to_status, self::RETURNABLE_STATES, true)),
            ],
            default => [],
        };
    }

    private function targetStatus(AuditEngagement $engagement, string $action): string
    {
        if ($action === 'SUSPEND' && in_array($engagement->status, self::SUSPENDABLE_STATES, true)) {
            return 'SUSPENDED';
        }
        if ($action === 'RESUME' && $engagement->status === 'SUSPENDED'
            && in_array($engagement->suspended_from_status, self::SUSPENDABLE_STATES, true)) {
            return $engagement->suspended_from_status;
        }
        if ($action === 'CANCEL' && in_array($engagement->status, self::CANCELLABLE_STATES, true)) {
            return 'CANCELLED';
        }
        if ($action === 'RETURN' && in_array($engagement->status, self::RETURNABLE_STATES, true)) {
            return 'RETURNED_FOR_REVISION';
        }
        if ($action === 'RESUBMIT' && $engagement->status === 'RETURNED_FOR_REVISION'
            && in_array($engagement->return_to_status, self::RETURNABLE_STATES, true)) {
            return $engagement->return_to_status;
        }

        $target = self::NORMAL_TRANSITIONS[$engagement->status][$action] ?? null;
        if (! $target) {
            throw ValidationException::withMessages([
                'action' => ["{$action} is not allowed while the engagement is {$engagement->status}."],
            ]);
        }

        return $target;
    }

    /** @return list<string> */
    private function candidateActions(AuditEngagement $engagement): array
    {
        $actions = array_keys(self::NORMAL_TRANSITIONS[$engagement->status] ?? []);
        if (in_array($engagement->status, self::RETURNABLE_STATES, true)) {
            $actions[] = 'RETURN';
        }
        if ($engagement->status === 'RETURNED_FOR_REVISION') {
            $actions[] = 'RESUBMIT';
        }
        if (in_array($engagement->status, self::SUSPENDABLE_STATES, true)) {
            $actions[] = 'SUSPEND';
        }
        if ($engagement->status === 'SUSPENDED') {
            $actions[] = 'RESUME';
        }
        if (in_array($engagement->status, self::CANCELLABLE_STATES, true)) {
            $actions[] = 'CANCEL';
        }

        return array_values(array_unique($actions));
    }

    private function authorizeAction(User $user, AuditEngagement $engagement, string $action): void
    {
        $ability = match ($action) {
            'ISSUE_AUTHORIZATION' => 'authorize',
            'SUSPEND' => 'suspend',
            'CANCEL' => 'cancel',
            'SUBMIT_FOR_CLOSURE' => 'close',
            default => 'transition',
        };
        Gate::forUser($user)->authorize($ability, $engagement);
    }

    private function canPerform(User $user, AuditEngagement $engagement, string $action): bool
    {
        $ability = match ($action) {
            'ISSUE_AUTHORIZATION' => 'authorize',
            'SUSPEND' => 'suspend',
            'CANCEL' => 'cancel',
            'SUBMIT_FOR_CLOSURE' => 'close',
            default => 'transition',
        };

        return Gate::forUser($user)->allows($ability, $engagement);
    }

    /** @return list<array{key: string, label: string, met: bool, link?: string}> */
    private function finalReportRequirements(AuditEngagement $engagement): array
    {
        $report = $engagement->reports
            ->where('report_stage', 'FINAL_REPORT')
            ->where('status', 'ISSUED')
            ->sortByDesc('current_version_number')
            ->first();
        $version = $report?->currentVersion;
        $findings = $version?->findings ?? collect();
        $recommendations = $findings->flatMap->recommendations;

        return [
            $this->gate('report', 'Current Final Report is approved and issued', $report !== null, 'reports'),
            $this->gate('recipients', 'Required recipients and issuance are recorded', $version
                && $version->recipients->isNotEmpty(), 'reports'),
            $this->gate('confidentiality', 'Report confidentiality is assigned', $report?->confidentiality_level_id !== null, 'reports'),
            $this->gate('findings', 'All included findings are finalized', $findings->every('status', 'FINALIZED'), 'findings'),
            $this->gate(
                'cms',
                'Included recommendations are transferred or formally excluded',
                $recommendations->every(
                    fn ($recommendation) => in_array($recommendation->status, ['TRANSFERRED', 'EXCLUDED'], true),
                ),
                'reports',
            ),
        ];
    }

    /** @return list<array{key: string, label: string, met: bool, link?: string}> */
    private function closureRequirements(AuditEngagement $engagement): array
    {
        $final = $this->finalReportRequirements($engagement);
        $procedures = $engagement->programs->flatMap->procedures;

        return [
            ...$final,
            $this->gate(
                'workingPapers',
                'Working Papers are terminal',
                $engagement->workingPapers->every(
                    fn ($paper) => in_array($paper->status, ['APPROVED', 'SUPERSEDED', 'VOIDED'], true),
                ),
                'working-papers',
            ),
            $this->gate(
                'procedures',
                'Procedures are completed or waived',
                $procedures->every(
                    fn ($procedure) => in_array($procedure->status, ['COMPLETED', 'WAIVED'], true),
                ),
                'audit-program',
            ),
            $this->gate(
                'entryConference',
                'Entry Conference is completed or waived',
                in_array($engagement->entryConference?->status, EntryConference::TERMINAL_STATUSES, true),
            ),
            $this->gate(
                'exitConference',
                'Exit Conference is completed or waived',
                $engagement->exitConferences->contains(
                    fn ($conference) => in_array($conference->status, ['COMPLETED', 'WAIVED'], true),
                ),
                'conferences',
            ),
            $this->gate('personDays', 'Actual person-days are recorded', (float) $engagement->actual_person_days > 0),
        ];
    }

    private function load(AuditEngagement $engagement): AuditEngagement
    {
        return $engagement->fresh([
            'offices:id,code,name',
            'auditAreas:id,code,name',
            'teamMembers.user:id,name',
            'engagementOrder.distributions',
            'engagementPlan',
            'planningPackage.latestVersion',
            'programs.procedures',
            'workingPapers',
            'issues',
            'findings.recommendations',
            'entryConference',
            'exitConferences',
            'reports.currentVersion.findings.recommendations',
            'reports.currentVersion.recipients',
            'events.actor:id,name',
        ]);
    }

    /**
     * Evaluate controls that are configured in the immutable Planning Package
     * rather than treating them as unconditional lifecycle passes. Older
     * packages did not carry these controls; those packages remain compatible
     * and are explicitly reported as not configured/not applicable.
     *
     * @return array{key: string, label: string, met: bool, link?: string}
     */
    private function planningControlGate(
        AuditEngagement $engagement,
        string $key,
        string $label,
        string $link,
    ): array {
        $version = $engagement->planningPackage?->latestVersion;
        if (! $version) {
            return $this->gate($key, "{$label} (not configured for this legacy planning package)", true, $link);
        }

        $attributes = is_array($version->planning_attributes) ? $version->planning_attributes : [];
        $config = data_get($attributes, $key);
        if (! is_array($config)) {
            $config = [];
        }
        $decision = strtoupper((string) ($config['decision'] ?? data_get($attributes, "{$key}Decision") ?? 'NOT_CONFIGURED'));

        if ($decision === 'NOT_APPLICABLE') {
            $reason = trim((string) ($config['reason'] ?? data_get($attributes, "{$key}NotApplicableReason") ?? ''));
            return $this->gate(
                $key,
                $reason !== '' ? "{$label} is formally not applicable ({$reason})" : "{$label} requires a not-applicable reason",
                $reason !== '',
                $link,
            );
        }

        if ($decision === 'NOT_CONFIGURED') {
            return $this->gate($key, "{$label} is not configured in the current planning baseline", true, $link);
        }

        if ($key === 'kpis') {
            $items = $config['items'] ?? data_get($attributes, 'kpis', []);
            if (! is_array($items)) {
                $items = [];
            }
            $complete = $items !== [] && collect($items)->every(function (mixed $item): bool {
                if (! is_array($item)) {
                    return false;
                }
                $name = trim((string) ($item['name'] ?? $item['indicator'] ?? ''));
                $target = $item['target'] ?? $item['targetValue'] ?? null;
                $method = trim((string) ($item['measurementMethod'] ?? $item['method'] ?? ''));
                return $name !== '' && $target !== null && $target !== '' && $method !== '';
            });

            return $this->gate(
                $key,
                $complete ? $label : "{$label} (each KPI needs a name, target, and measurement method)",
                $complete,
                $link,
            );
        }

        $status = strtoupper((string) ($config['status'] ?? data_get($attributes, 'progressAssessmentStatus', '')));
        $reference = trim((string) ($config['evidenceReference'] ?? $config['reference'] ?? data_get($attributes, 'progressAssessmentReference', '')));
        $met = in_array($decision, ['REQUIRED', 'RECORDED', 'COMPLETE', 'COMPLETED', 'DEFINED'], true)
            && ($status === '' || in_array($status, ['RECORDED', 'COMPLETE', 'COMPLETED', 'DEFINED'], true))
            && $reference !== '';

        return $this->gate(
            $key,
            $met ? $label : "{$label} (record a status and evidence reference)",
            $met,
            $link,
        );
    }

    private function validSource(AuditEngagement $engagement): bool
    {
        return $engagement->source_type === 'PLANNED'
            ? $engagement->iap_plan_engagement_id !== null && $engagement->source_snapshot !== null
            : filled($engagement->special_authority_reference)
                && $engagement->special_authority_date !== null
                && $engagement->special_authority_approved_by !== null;
    }

    /** @return array{key: string, label: string, met: bool, link?: string} */
    private function gate(string $key, string $label, bool $met, ?string $link = null): array
    {
        return array_filter([
            'key' => $key,
            'label' => $label,
            'met' => $met,
            'link' => $link
                ? "/audit-engagement-management/{$link}"
                : null,
        ], fn ($value) => $value !== null);
    }

    /** @return list<array{label: string, path: string}> */
    private function relatedLinks(AuditEngagement $engagement): array
    {
        $id = $engagement->id;

        return [
            ['label' => 'Audit Team', 'path' => "/audit-engagement-management/team?engagementId={$id}"],
            ['label' => 'AEO', 'path' => "/audit-engagement-management/aeo?engagementId={$id}"],
            ['label' => 'AEP', 'path' => "/audit-engagement-management/aep?engagementId={$id}"],
            ['label' => 'Planning Workspace', 'path' => "/audit-engagement-management/planning-package?engagementId={$id}"],
            ['label' => 'Audit Program', 'path' => "/audit-engagement-management/audit-program?engagementId={$id}"],
            ['label' => 'Working Papers', 'path' => "/audit-engagement-management/working-papers?engagementId={$id}"],
            ['label' => 'Findings', 'path' => "/audit-engagement-management/findings?engagementId={$id}"],
            ['label' => 'Conference Management', 'path' => "/audit-engagement-management/conferences?engagementId={$id}"],
            ['label' => 'Reports', 'path' => "/audit-engagement-management/reports?engagementId={$id}"],
            ['label' => 'Completion & Closure', 'path' => "/audit-engagement-management/{$id}?tab=closure"],
        ];
    }

    private function validateComment(string $action, array $details): void
    {
        if (in_array(
            $action,
            ['RETURN', 'RESUBMIT', 'SUSPEND', 'RESUME', 'CANCEL', 'SUBMIT_FOR_CLOSURE'],
            true,
        ) && blank($details['comment'] ?? null)) {
            throw ValidationException::withMessages([
                'comment' => ["A comment is required for {$action}."],
            ]);
        }
        if ($action === 'SUSPEND' && blank($details['authority'] ?? null)) {
            throw ValidationException::withMessages([
                'authority' => ['Suspension authority is required.'],
            ]);
        }
        if ($action === 'SUSPEND'
            && (blank($details['effectiveDate'] ?? null)
                || blank($details['expectedReviewDate'] ?? null)
                || blank($details['resumeRequirements'] ?? null))) {
            throw ValidationException::withMessages([
                'effectiveDate' => ['Suspension effective date is required.'],
                'expectedReviewDate' => ['Suspension review date is required.'],
                'resumeRequirements' => ['Suspension resume requirements are required.'],
            ]);
        }
        if ($action === 'CANCEL'
            && (blank($details['authority'] ?? null)
                || blank($details['effectOnIap'] ?? null)
                || blank($details['workProductDisposition'] ?? null))) {
            throw ValidationException::withMessages([
                'authority' => ['Cancellation authority is required.'],
                'effectOnIap' => ['Record the cancellation effect on IAP.'],
                'workProductDisposition' => ['Record the disposition of work products and documents.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(AuditEngagement $engagement): array
    {
        return [
            'status' => $engagement->status,
            'phase' => $engagement->phase,
            'administrativeStatus' => $engagement->administrative_status,
            'returnedFromStatus' => $engagement->returned_from_status,
            'returnToStatus' => $engagement->return_to_status,
            'suspendedFromStatus' => $engagement->suspended_from_status,
            'statusReason' => $engagement->status_reason,
            'suspensionMetadata' => $engagement->suspension_metadata,
            'cancellationMetadata' => $engagement->cancellation_metadata,
            'actualStartDate' => $engagement->actual_start_date?->toDateString(),
            'lockVersion' => $engagement->lock_version,
            'isActive' => $engagement->is_active,
        ];
    }
}
