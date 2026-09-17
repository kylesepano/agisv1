<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/** Complete safe CMS-2A workspace assembled from case, intake, and lineage snapshots. */
class CmsRecommendationDetailResource extends CmsRecommendationResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $base = parent::toArray($request);
        $intake = $this->recommendation;
        $snapshot = $intake->source_snapshot ?? [];

        return [
            ...$base,
            'intake' => [
                'id' => $intake->id,
                'transferKey' => $intake->transfer_key,
                'transferredAt' => $intake->transferred_at?->toISOString(),
                'transferredBy' => $this->safeUser($intake->transferActor),
                'sourceSchemaVersion' => $intake->source_schema_version,
                'originalTargetDate' => $intake->original_target_implementation_date?->toDateString(),
                'responsibleOfficeSnapshot' => $intake->responsible_office_snapshot,
                'confidentialitySnapshot' => [
                    'id' => $intake->confidentiality_level_id,
                    'code' => $intake->confidentiality_code_snapshot,
                    'label' => $intake->confidentiality_label_snapshot,
                ],
                'riskSnapshot' => [
                    'id' => $intake->risk_rating_id,
                    'code' => $intake->risk_code_snapshot,
                    'label' => $intake->risk_label_snapshot,
                ],
            ],
            'sourceLineage' => [
                'engagement' => [
                    'id' => $intake->audit_engagement_id,
                    'code' => data_get($snapshot, 'engagement.code'),
                    'title' => data_get($snapshot, 'engagement.title'),
                ],
                'finding' => [
                    'id' => $intake->audit_finding_id,
                    'code' => data_get($snapshot, 'finding.code'),
                    'title' => data_get($snapshot, 'finding.title'),
                ],
                'recommendation' => [
                    'id' => $intake->source_audit_recommendation_id,
                    'code' => $intake->recommendation_code,
                    'wording' => data_get($snapshot, 'recommendation.wording'),
                ],
                'report' => [
                    'id' => $intake->audit_report_id,
                    'finalReportNumber' => $intake->report_code_snapshot,
                    'versionId' => $intake->audit_report_version_id,
                    'versionNumber' => $intake->report_version_number_snapshot,
                    'issuedAt' => $intake->report_issued_at?->toISOString(),
                    'checksumSha256' => $intake->report_checksum_sha256,
                ],
            ],
            'officeAccountability' => [
                'leadResponsibleOffice' => $this->leadResponsibleOffice?->only([
                    'id', 'code', 'name', 'acronym',
                ]),
                'originalResponsibleOffices' => $intake->responsible_office_snapshot,
            ],
            'automationSummary' => $request->user()?->hasPermission('cms.automation.view') ? [
                'closureCandidates' => $this->whenLoaded('closureCandidates', fn () => $this->closureCandidates->map(fn ($candidate): array => [
                    'id' => $candidate->id,
                    'statusCode' => $candidate->status_code,
                    'detectedAt' => $candidate->detected_at?->toISOString(),
                    'readiness' => $candidate->readiness_snapshot,
                ])->values()),
                'escalationCandidates' => $this->whenLoaded('escalationCandidates', fn () => $this->escalationCandidates->map(fn ($candidate): array => [
                    'id' => $candidate->id,
                    'statusCode' => $candidate->status_code,
                    'triggerCode' => $candidate->trigger_code,
                    'severityCode' => $candidate->severity_code,
                    'reason' => $candidate->reason,
                    'detectedAt' => $candidate->detected_at?->toISOString(),
                ])->values()),
            ] : null,
            'assignments' => CmsRecommendationAssignmentResource::collection(
                $this->whenLoaded('assignments'),
            ),
            'timeline' => $this->whenLoaded('events', fn () => $this->events->map(
                fn ($event): array => [
                    'id' => $event->id,
                    'eventCode' => $event->event_code,
                    'sourceModule' => $event->source_module,
                    'previousStatus' => $event->previous_status,
                    'newStatus' => $event->new_status,
                    'metadata' => $event->event_metadata,
                    'createdAt' => $event->created_at?->toISOString(),
                    'actor' => $this->safeUser($event->actor),
                ],
            )->values()),
            'actionPlanSummary' => $this->whenLoaded('actionPlan', function () use ($request): array {
                $plan = $this->actionPlan;
                if (! $plan) {
                    return [
                        'hasActionPlan' => false,
                        'permittedActions' => [],
                    ];
                }
                $current = $plan->currentVersion;
                $accepted = $plan->acceptedVersion;
                if ($request->user()->hasPermission('access.read_only_scope')
                    && $current
                    && ! in_array($current->status_code, [
                        'SUBMITTED',
                        'UNDER_REVIEW',
                        'ACCEPTED',
                    ], true)) {
                    $current = null;
                }

                return [
                    'hasActionPlan' => true,
                    'actionPlanId' => $plan->id,
                    'currentVersionId' => $plan->current_version_id,
                    'currentVersionNumber' => $current?->version_number,
                    'currentVersionStatus' => $current?->status_code,
                    'acceptedVersionId' => $plan->accepted_version_id,
                    'acceptedVersionNumber' => $accepted?->version_number,
                    'milestoneCount' => $current?->milestones?->count() ?? 0,
                    'latestSubmittedAt' => $current?->submitted_at?->toISOString(),
                    'latestAcceptedAt' => $accepted?->accepted_at?->toISOString(),
                    'permittedActions' => $current?->getAttribute('available_actions') ?? [],
                ];
            }),
            'progressUpdateSummary' => $this->whenLoaded(
                'progressUpdates',
                function () use ($request): array {
                    $visibleStatuses = $request->user()->hasPermission('access.read_only_scope')
                        ? ['SUBMITTED', 'UNDER_REVIEW', 'RECORDED']
                        : ['DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED', 'RECORDED'];
                    $updates = $this->progressUpdates->filter(
                        fn ($update): bool => $update->currentVersion
                            && in_array(
                                $update->currentVersion->status_code,
                                $visibleStatuses,
                                true,
                            ),
                    )->values();
                    $latest = $updates->first();
                    $latestRecorded = $updates->first(
                        fn ($update): bool => $update->recordedVersion !== null,
                    );
                    $percentage = $latest?->currentVersion
                        ?->management_reported_overall_percentage;

                    return [
                        'hasProgressUpdates' => $updates->isNotEmpty(),
                        'latestProgressUpdateId' => $latest?->id,
                        'latestReportingPeriodEnd' => $latest
                            ?->reporting_period_end
                            ?->toDateString(),
                        'latestCurrentVersionStatus' => $latest
                            ?->currentVersion
                            ?->status_code,
                        'latestRecordedVersionId' => $latestRecorded?->recorded_version_id,
                        'latestManagementReportedPercentage' => $percentage,
                        'managementReportsComplete' => $percentage !== null
                            && (float) $percentage >= 100,
                        'reportedCompleteAwaitingValidation' => $percentage !== null
                            && (float) $percentage >= 100,
                        'evidenceLinkCount' => $latest
                            ?->currentVersion
                            ?->activeEvidenceLinks
                            ?->count() ?? 0,
                        'updatesAwaitingReview' => $updates->filter(
                            fn ($update): bool => in_array(
                                $update->currentVersion?->status_code,
                                ['SUBMITTED', 'UNDER_REVIEW'],
                                true,
                            ),
                        )->count(),
                        'permittedActions' => $latest
                            ?->currentVersion
                            ?->getAttribute('available_actions') ?? [],
                        'notIndependentlyValidated' => true,
                    ];
                },
            ),
            'validationSummary' => $this->whenLoaded(
                'validationReviews',
                function () use ($request): array {
                    $restrictedRead = $request->user()->hasAnyPermission([
                        'access.read_only_scope',
                        'access.auditee_scope',
                    ]);
                    $reviews = $restrictedRead
                        ? $this->validationReviews->filter(
                            fn ($review): bool => $review->finalizedVersion !== null,
                        )->values()
                        : $this->validationReviews;
                    $latest = $reviews->first();
                    $current = $latest?->currentVersion;
                    $finalized = $latest?->finalizedVersion;

                    return [
                        'hasValidationReviews' => $reviews->isNotEmpty(),
                        'latestValidationReviewId' => $latest?->id,
                        'latestValidationVersionStatus' => $current?->status_code,
                        'currentAssignedValidator' => $latest?->currentAssignment?->user
                            ? $this->safeUser($latest->currentAssignment->user)
                            : null,
                        'latestProposedConclusion' => $restrictedRead
                            ? null
                            : $current?->proposed_conclusion_code,
                        'latestFinalizedConclusion' => $finalized?->final_conclusion_code,
                        'latestFinalizedAt' => $finalized?->finalized_at?->toISOString(),
                        'awaitingSupervisoryReview' => $current?->status_code === 'SUBMITTED',
                        'returnedForRevision' => $restrictedRead
                            ? false
                            : $current?->status_code === 'RETURNED',
                        'latestValidatedCompletionPercentage' => $finalized
                            ?->validated_completion_percentage,
                        'permittedActions' => $restrictedRead
                            ? []
                            : $current?->getAttribute('available_actions') ?? [],
                        'implementedDoesNotMeanClosed' => $finalized
                            ?->final_conclusion_code === 'IMPLEMENTED',
                    ];
                },
            ),
            'targetDateExtensionSummary' => $this->whenLoaded(
                'targetDateExtensionRequests',
                fn (): array => [
                    'requestCount' => $this->targetDateExtensionRequests->count(),
                    'openRequestCount' => $this->targetDateExtensionRequests->filter(
                        fn ($extension): bool => $extension->resolved_at === null,
                    )->count(),
                    'approvedRequestCount' => $this->targetDateExtensionRequests->filter(
                        fn ($extension): bool => $extension->resolvedVersion?->status_code === 'APPROVED',
                    )->count(),
                    'currentEffectiveTargetDate' => $this->effective_target_implementation_date?->toDateString(),
                    'originalTargetDate' => $this->recommendation?->original_target_implementation_date?->toDateString(),
                    'requests' => CmsTargetDateExtensionResource::collection($this->targetDateExtensionRequests),
                    'history' => CmsRecommendationTargetDateHistoryResource::collection(
                        $this->whenLoaded('targetDateHistory'),
                    ),
                ],
            ),
            'escalationSummary' => $this->whenLoaded(
                'escalations',
                fn (): array => [
                    'hasEscalations' => $this->escalations->isNotEmpty(),
                    'activeEscalationId' => $this->escalations->first(fn ($e): bool => $e->resolved_at === null)?->id,
                    'activeOperationalStatus' => $this->escalations->first(fn ($e): bool => $e->resolved_at === null)?->operational_status_code,
                    'primaryTriggerCode' => $this->escalations->first(fn ($e): bool => $e->resolved_at === null)?->primary_trigger_code,
                    'priorEscalationCount' => $this->escalations->count(),
                    'escalations' => CmsEscalationResource::collection($this->escalations),
                ],
            ),
            'closureSummary' => $this->whenLoaded(
                'closureRequests',
                fn (): array => [
                    'hasClosureRequests' => $this->closureRequests->isNotEmpty(),
                    'activeRequestId' => $this->closureRequests->first(fn ($r): bool => $r->resolved_at === null)?->id,
                    'activeRequestStatus' => $this->closureRequests->first(fn ($r): bool => $r->resolved_at === null)?->currentVersion?->status_code,
                    'priorRequestCount' => $this->closureRequests->count(),
                    'formallyClosed' => $this->status_code === 'CLOSED',
                    'closedAt' => $this->closed_at?->toISOString(),
                    'closureDecisionId' => $this->closure_decision_id,
                ],
            ),
            'dispositionSummary' => $this->whenLoaded(
                'dispositionRequests',
                fn (): array => [
                    'hasDispositionRequests' => $this->dispositionRequests->isNotEmpty(),
                    'activeRequestId' => $this->dispositionRequests->first(fn ($r): bool => $r->resolved_at === null)?->id,
                    'activeRequestStatus' => $this->dispositionRequests->first(fn ($r): bool => $r->resolved_at === null)?->currentVersion?->status_code,
                    'activeDispositionCode' => $this->dispositionRequests->first(fn ($r): bool => $r->resolved_at === null)?->disposition_code,
                    'priorRequestCount' => $this->dispositionRequests->count(),
                    'acceptedRisk' => $this->status_code === 'ACCEPTED_RISK',
                    'noLongerApplicable' => $this->status_code === 'NO_LONGER_APPLICABLE',
                    'requests' => CmsDispositionRequestResource::collection($this->dispositionRequests),
                ],
            ),
            'reopeningSummary' => $this->whenLoaded(
                'reopeningRequests',
                fn (): array => [
                    'hasReopeningRequests' => $this->reopeningRequests->isNotEmpty(),
                    'activeRequestId' => $this->reopeningRequests->first(fn ($r): bool => $r->resolved_at === null)?->id,
                    'activeRequestStatus' => $this->reopeningRequests->first(fn ($r): bool => $r->resolved_at === null)?->currentVersion?->status_code,
                    'sourceTerminalStatus' => $this->reopeningRequests->first()?->source_terminal_status,
                    'priorRequestCount' => $this->reopeningRequests->count(),
                    'wasPreviouslyClosed' => $this->reopeningRequests->contains(fn ($r): bool => $r->source_terminal_status === 'CLOSED'),
                    'wasPreviouslyAcceptedRisk' => $this->reopeningRequests->contains(fn ($r): bool => $r->source_terminal_status === 'ACCEPTED_RISK'),
                    'wasPreviouslyNoLongerApplicable' => $this->reopeningRequests->contains(fn ($r): bool => $r->source_terminal_status === 'NO_LONGER_APPLICABLE'),
                    'currentlyReopened' => (int) ($this->reopening_count ?: 0) > 0 && ! in_array($this->status_code, ['CLOSED', 'ACCEPTED_RISK', 'NO_LONGER_APPLICABLE', 'CANCELLED'], true),
                    'activeCycleNumber' => (int) ($this->active_cycle_number ?: 1),
                    'latestReopeningDate' => $this->last_reopened_at?->toISOString(),
                    'latestDecision' => $this->lastReopeningDecision ? new CmsReopeningDecisionResource($this->lastReopeningDecision) : null,
                    'requests' => CmsReopeningRequestResource::collection($this->reopeningRequests),
                ],
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    private function safeUser(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }
}
