<?php

namespace App\Services\Cms;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\AuditLog;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CmsRecommendation;
use App\Models\CmsRecommendationCase;
use App\Models\CmsRecommendationEvent;
use App\Models\Office;
use App\Models\User;
use App\Services\AemsAccessService;
use App\Support\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Authoritative trust boundary for immutable AEMS-to-CMS recommendation intake.
 */
class CmsIntakeService
{
    private const ACTION_INTAKE_CREATED = 'cms.recommendation.intake_created';

    public function __construct(private readonly AemsAccessService $access) {}

    public function intake(
        AuditRecommendation $recommendation,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
        Request $request,
    ): CmsRecommendation {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('CMS intake must execute inside an active database transaction.');
        }

        $actor = $request->user();
        throw_unless($actor instanceof User, new LogicException('CMS intake requires an authenticated actor.'));

        [
            $engagement,
            $report,
            $version,
            $finding,
            $recommendation,
        ] = $this->lockAndValidate(
            $recommendation,
            $engagement,
            $report,
            $version,
            $actor,
        );

        $transferKey = $recommendation->cms_transfer_key ?: (string) Str::uuid();
        $transferredAt = now();
        $source = $this->sourceData(
            $recommendation,
            $engagement,
            $report,
            $version,
            $finding,
            $actor,
            $transferKey,
            $transferredAt,
        );

        $createdIntake = DB::table('cms_recommendations')->insertOrIgnore([
            'source_audit_recommendation_id' => $recommendation->id,
            'transfer_key' => $transferKey,
            'audit_engagement_id' => $engagement->id,
            'audit_report_id' => $report->id,
            'audit_report_version_id' => $version->id,
            'report_code_snapshot' => $report->report_code,
            'report_version_number_snapshot' => $version->version_number,
            'report_issued_at' => $report->issued_at,
            'report_issued_by' => $report->issued_by,
            'report_checksum_sha256' => $version->checksum_sha256,
            'confidentiality_level_id' => $report->confidentiality_level_id,
            'confidentiality_code_snapshot' => $source['report']['confidentiality']['code'],
            'confidentiality_label_snapshot' => $source['report']['confidentiality']['label'],
            'audit_finding_id' => $finding->id,
            'risk_rating_id' => $finding->risk_rating_id,
            'risk_code_snapshot' => $source['finding']['risk']['code'],
            'risk_label_snapshot' => $source['finding']['risk']['label'],
            'recommendation_code' => $recommendation->recommendation_code,
            'source_snapshot' => json_encode($source, JSON_THROW_ON_ERROR),
            'responsible_office_id' => $recommendation->responsible_office_id,
            'responsible_office_snapshot' => json_encode(
                $source['recommendation']['responsibleOffices'],
                JSON_THROW_ON_ERROR,
            ),
            'lead_responsible_office_id' => $recommendation->responsible_office_id,
            'target_implementation_date' => $recommendation->target_implementation_date,
            'original_target_implementation_date' => $recommendation->target_implementation_date,
            'source_schema_version' => CmsRecommendation::SOURCE_SCHEMA_VERSION,
            'status' => CmsRecommendation::STATUS_TRANSFERRED,
            'transferred_at' => $transferredAt,
            'transferred_by' => $actor->id,
            'created_at' => $transferredAt,
            'updated_at' => $transferredAt,
        ]) === 1;

        $intake = CmsRecommendation::query()
            ->where('source_audit_recommendation_id', $recommendation->id)
            ->first();

        if (! $intake) {
            $keyOwner = CmsRecommendation::query()
                ->where('transfer_key', $transferKey)
                ->first();
            $message = $keyOwner
                ? "Transfer key {$transferKey} already belongs to CMS intake {$keyOwner->id}."
                : 'The conflict-safe CMS intake insert did not produce an authoritative record.';
            throw ValidationException::withMessages(['cmsTransfer' => [$message]]);
        }

        $this->assertMatchingIdentity(
            $intake,
            $recommendation,
            $engagement,
            $report,
            $version,
            $finding,
            $transferKey,
        );

        DB::table('cms_recommendation_cases')->insertOrIgnore([
            'cms_recommendation_id' => $intake->id,
            'status_code' => CmsRecommendationCase::STATUS_TRANSFERRED,
            'effective_target_implementation_date' => $intake->original_target_implementation_date,
            'lead_responsible_office_id' => $intake->lead_responsible_office_id,
            'opened_at' => $intake->transferred_at,
            'created_by' => $intake->transferred_by,
            'lock_version' => 1,
            'created_at' => $intake->transferred_at,
            'updated_at' => $intake->transferred_at,
        ]);
        $case = CmsRecommendationCase::query()
            ->where('cms_recommendation_id', $intake->id)
            ->firstOrFail();

        $eventMetadata = $this->eventMetadata($source, $intake, $case);
        DB::table('cms_recommendation_events')->insertOrIgnore([
            'cms_recommendation_case_id' => $case->id,
            'cms_recommendation_id' => $intake->id,
            'idempotency_key' => "cms-intake:{$intake->id}",
            'event_code' => CmsRecommendationEvent::EVENT_INTAKE_CREATED,
            'source_module' => 'AEMS',
            'actor_id' => $intake->transferred_by,
            'previous_status' => null,
            'new_status' => CmsRecommendationCase::STATUS_TRANSFERRED,
            'event_metadata' => json_encode($eventMetadata, JSON_THROW_ON_ERROR),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'created_at' => $intake->transferred_at,
        ]);

        $this->synchronizeAemsLineage($recommendation, $intake);

        if ($createdIntake) {
            $this->recordCreation($request, $intake, $case, $eventMetadata);
        }

        return $intake->fresh([
            'case.events',
            'engagement',
            'report',
            'reportVersion',
            'finding',
            'leadResponsibleOffice',
        ]);
    }

    /**
     * @return array{
     *   AuditEngagement,
     *   AuditReport,
     *   AuditReportVersion,
     *   AuditFinding,
     *   AuditRecommendation
     * }
     */
    private function lockAndValidate(
        AuditRecommendation $suppliedRecommendation,
        AuditEngagement $suppliedEngagement,
        AuditReport $suppliedReport,
        AuditReportVersion $suppliedVersion,
        User $actor,
    ): array {
        $engagement = AuditEngagement::query()
            ->lockForUpdate()
            ->findOrFail($suppliedEngagement->id);
        $report = AuditReport::query()
            ->lockForUpdate()
            ->findOrFail($suppliedReport->id);
        $version = AuditReportVersion::query()
            ->lockForUpdate()
            ->findOrFail($suppliedVersion->id);
        $recommendation = AuditRecommendation::query()
            ->withTrashed()
            ->lockForUpdate()
            ->findOrFail($suppliedRecommendation->id);
        $finding = AuditFinding::query()
            ->withTrashed()
            ->lockForUpdate()
            ->findOrFail($recommendation->audit_finding_id);

        $this->access->authorizeEngagementAction(
            $actor,
            $engagement,
            'aems.report.issue',
            $report->prepared_by,
        );

        $errors = [];
        if ((int) $report->audit_engagement_id !== (int) $engagement->id) {
            $errors[] = 'The Final Report does not belong to the supplied engagement.';
        }
        if ($report->report_stage !== 'FINAL_REPORT' || $report->status !== 'ISSUED') {
            $errors[] = 'CMS intake requires an issued Final Report.';
        }
        if ((int) $version->audit_report_id !== (int) $report->id
            || (int) $report->current_version_id !== (int) $version->id) {
            $errors[] = 'CMS intake requires the exact current issued report version.';
        }
        if ($version->report_stage !== 'FINAL_REPORT'
            || ! $version->is_locked
            || ! $version->locked_at) {
            $errors[] = 'The exact Final Report version must be locked before CMS intake.';
        }
        if ((int) $finding->audit_engagement_id !== (int) $engagement->id) {
            $errors[] = 'The finding does not belong to the supplied engagement.';
        }
        if ($finding->trashed()
            || ! $finding->is_current_revision
            || $finding->status !== 'FINALIZED') {
            $errors[] = 'CMS intake requires a current, finalized, non-archived finding.';
        }
        $included = DB::table('audit_report_findings')
            ->where('audit_report_version_id', $version->id)
            ->where('audit_finding_id', $finding->id)
            ->where('is_included', true)
            ->exists();
        if (! $included) {
            $errors[] = 'The finding is not included in the exact issued report version.';
        }
        if ((int) $recommendation->audit_finding_id !== (int) $finding->id
            || (int) $suppliedRecommendation->audit_finding_id !== (int) $finding->id) {
            $errors[] = 'The recommendation does not belong to the supplied finding.';
        }
        if ($recommendation->trashed()) {
            $errors[] = 'Archived recommendations cannot enter CMS.';
        }
        if (! in_array($recommendation->status, ['FINALIZED', 'TRANSFERRED'], true)) {
            $errors[] = 'Only finalized recommendations may enter CMS.';
        }
        if ($recommendation->status === 'EXCLUDED'
            || $recommendation->cms_excluded_at
            || filled($recommendation->cms_exclusion_reason)
            || filled($recommendation->cms_exclusion_authority)) {
            $errors[] = 'Formally excluded recommendations do not create CMS records.';
        }
        if ((int) $finding->id !== (int) $suppliedRecommendation->finding?->id) {
            $errors[] = 'The supplied recommendation/finding relationship is inconsistent.';
        }

        $this->validateFinalizedRecommendationSnapshot($recommendation, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages(['cmsTransfer' => $errors]);
        }

        return [$engagement, $report, $version, $finding, $recommendation];
    }

    /** @param list<string> $errors */
    private function validateFinalizedRecommendationSnapshot(
        AuditRecommendation $recommendation,
        array &$errors,
    ): void {
        $snapshot = $recommendation->finalized_snapshot;
        if (! is_array($snapshot)) {
            return;
        }

        $checks = [
            'id' => $recommendation->id,
            'recommendationCode' => $recommendation->recommendation_code,
            'recommendation' => $recommendation->recommendation,
            'responsibleOfficeId' => $recommendation->responsible_office_id,
            'targetImplementationDate' => $recommendation->target_implementation_date?->toDateString(),
        ];
        foreach ($checks as $key => $expected) {
            if (array_key_exists($key, $snapshot) && $snapshot[$key] != $expected) {
                $errors[] = "The finalized recommendation snapshot conflicts with {$key}.";
            }
        }
    }

    /** @return array<string, mixed> */
    private function sourceData(
        AuditRecommendation $recommendation,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
        AuditFinding $finding,
        User $actor,
        string $transferKey,
        mixed $transferredAt,
    ): array {
        $finding->loadMissing(['riskRating', 'responsibleOffice']);
        $recommendation->loadMissing('responsibleOffice');
        $report->loadMissing(['confidentialityLevel', 'issuer']);
        $engagement->loadMissing([
            'offices',
            'sourcePlanEngagement',
            'sourcePlan',
            'sourcePrioritizationItem',
            'sourceRiskAssessment',
            'sourceAuditUniverseItem',
        ]);

        $sourceSnapshotHash = $engagement->source_snapshot === null
            ? null
            : hash(
                'sha256',
                json_encode($engagement->source_snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            );

        $leadOffice = $this->officeSnapshot($recommendation->responsibleOffice, true);
        $responsibleOffices = $leadOffice ? [$leadOffice] : [];

        return [
            'sourceSchemaVersion' => CmsRecommendation::SOURCE_SCHEMA_VERSION,
            'engagement' => [
                'id' => $engagement->id,
                'code' => $engagement->engagement_code,
                'title' => $engagement->title,
                'offices' => $engagement->offices
                    ->map(fn (Office $office): array => [
                        ...$this->officeSnapshot($office),
                        'isPrimary' => (bool) $office->pivot?->is_primary,
                    ])->values()->all(),
                // AEMS source lineage is copied into the immutable CMS
                // envelope.  CMS can audit where the issued recommendation
                // originated without querying or mutating IAP records.
                'sourceLineage' => [
                    'sourceType' => $engagement->source_type,
                    'iapPlanEngagementId' => $engagement->iap_plan_engagement_id,
                    'iapPlanId' => $engagement->iap_plan_id,
                    'iapPrioritizationItemId' => $engagement->iap_prioritization_item_id,
                    'iapRiskAssessmentId' => $engagement->iap_risk_assessment_id,
                    'iapAuditUniverseItemId' => $engagement->iap_audit_universe_item_id,
                    'sourceSnapshotHash' => $sourceSnapshotHash,
                ],
            ],
            'finding' => [
                'id' => $finding->id,
                'code' => $finding->finding_code,
                'title' => $finding->title,
                'risk' => [
                    'id' => $finding->riskRating?->id,
                    'code' => $finding->riskRating?->code,
                    'label' => $finding->riskRating?->label,
                ],
                'responsibleOffice' => $this->officeSnapshot($finding->responsibleOffice),
                'finalizedAt' => $finding->finalized_at?->toIso8601String(),
                'finalizedBy' => $finding->finalized_by,
            ],
            'recommendation' => [
                'id' => $recommendation->id,
                'code' => $recommendation->recommendation_code,
                'wording' => $recommendation->recommendation,
                'responsibleOffices' => $responsibleOffices,
                'leadResponsibleOfficeId' => $recommendation->responsible_office_id,
                'originalTargetImplementationDate' => $recommendation->target_implementation_date?->toDateString(),
                'finalizedAt' => $recommendation->finalized_at?->toIso8601String(),
                'finalizedBy' => $recommendation->finalized_by,
            ],
            'report' => [
                'id' => $report->id,
                'code' => $report->report_code,
                'versionId' => $version->id,
                'versionNumber' => $version->version_number,
                'issuedAt' => $report->issued_at?->toIso8601String(),
                'issuedBy' => $report->issued_by,
                'checksumSha256' => $version->checksum_sha256,
                'confidentiality' => [
                    'id' => $report->confidentialityLevel?->id,
                    'code' => $report->confidentialityLevel?->code,
                    'label' => $report->confidentialityLevel?->label,
                ],
            ],
            'transfer' => [
                'key' => $transferKey,
                'actor' => [
                    'id' => $actor->id,
                    'name' => $actor->name,
                    'officeId' => $actor->office_id,
                ],
                'transferredAt' => $transferredAt->toIso8601String(),
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function officeSnapshot(?Office $office, bool $isLead = false): ?array
    {
        return $office ? [
            'id' => $office->id,
            'code' => $office->code,
            'name' => $office->name,
            'acronym' => $office->acronym,
            'isLead' => $isLead,
        ] : null;
    }

    private function assertMatchingIdentity(
        CmsRecommendation $intake,
        AuditRecommendation $recommendation,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
        AuditFinding $finding,
        string $requestedTransferKey,
    ): void {
        $identity = [
            'source recommendation' => [(int) $intake->source_audit_recommendation_id, (int) $recommendation->id],
            'engagement' => [(int) $intake->audit_engagement_id, (int) $engagement->id],
            'report' => [(int) $intake->audit_report_id, (int) $report->id],
            'report version' => [(int) $intake->audit_report_version_id, (int) $version->id],
            'finding' => [(int) $intake->audit_finding_id, (int) $finding->id],
        ];
        $conflicts = collect($identity)
            ->filter(fn (array $values): bool => $values[0] !== $values[1])
            ->keys()
            ->values()
            ->all();

        if ($intake->recommendation_code !== $recommendation->recommendation_code) {
            $conflicts[] = 'recommendation code';
        }
        if (($intake->source_snapshot['recommendation']['wording'] ?? null)
            !== $recommendation->recommendation) {
            $conflicts[] = 'recommendation wording';
        }
        if ((int) $intake->lead_responsible_office_id
            !== (int) $recommendation->responsible_office_id) {
            $conflicts[] = 'lead responsible office';
        }
        if ($intake->original_target_implementation_date?->toDateString()
            !== $recommendation->target_implementation_date?->toDateString()) {
            $conflicts[] = 'original target date';
        }
        if ($intake->report_checksum_sha256 !== $version->checksum_sha256) {
            $conflicts[] = 'report checksum';
        }
        if ((int) $intake->confidentiality_level_id
            !== (int) $report->confidentiality_level_id) {
            $conflicts[] = 'confidentiality';
        }
        if ((int) $intake->risk_rating_id !== (int) $finding->risk_rating_id) {
            $conflicts[] = 'risk rating';
        }
        if ($recommendation->cms_transfer_key
            && ! hash_equals($intake->transfer_key, $requestedTransferKey)) {
            $conflicts[] = 'transfer key';
        }
        if ($intake->status !== CmsRecommendation::STATUS_TRANSFERRED) {
            $conflicts[] = 'intake status';
        }

        if ($conflicts !== []) {
            throw ValidationException::withMessages([
                'cmsTransfer' => [
                    'Existing CMS intake conflicts with the current immutable source identity: '
                    .implode(', ', array_unique($conflicts)).'.',
                ],
            ]);
        }
    }

    private function synchronizeAemsLineage(
        AuditRecommendation $recommendation,
        CmsRecommendation $intake,
    ): void {
        if ($recommendation->cms_recommendation_id
            && (int) $recommendation->cms_recommendation_id !== (int) $intake->id) {
            throw ValidationException::withMessages([
                'cmsTransfer' => ['The AEMS recommendation points to a different CMS intake.'],
            ]);
        }
        if ($recommendation->cms_transfer_key
            && ! hash_equals($recommendation->cms_transfer_key, $intake->transfer_key)) {
            throw ValidationException::withMessages([
                'cmsTransfer' => ['The AEMS recommendation has a conflicting CMS transfer key.'],
            ]);
        }

        if ($recommendation->status !== 'TRANSFERRED'
            || ! $recommendation->cms_recommendation_id
            || ! $recommendation->cms_transfer_key
            || ! $recommendation->transferred_to_cms_at) {
            $recommendation->forceFill([
                'status' => 'TRANSFERRED',
                'cms_transfer_key' => $intake->transfer_key,
                'cms_recommendation_id' => $intake->id,
                'transferred_to_cms_at' => $intake->transferred_at,
                'transferred_to_cms_by' => $intake->transferred_by,
                'lock_version' => $recommendation->lock_version + 1,
            ])->save();
        }
    }

    /** @return array<string, mixed> */
    private function eventMetadata(
        array $source,
        CmsRecommendation $intake,
        CmsRecommendationCase $case,
    ): array {
        return [
            'engagementId' => $source['engagement']['id'],
            'aemsSourceLineage' => $source['engagement']['sourceLineage'] ?? null,
            'reportId' => $source['report']['id'],
            'reportVersionId' => $source['report']['versionId'],
            'findingId' => $source['finding']['id'],
            'recommendationId' => $source['recommendation']['id'],
            'cmsRecommendationId' => $intake->id,
            'cmsRecommendationCaseId' => $case->id,
            'transferKey' => $intake->transfer_key,
            'leadResponsibleOfficeId' => $source['recommendation']['leadResponsibleOfficeId'],
            'originalTargetImplementationDate' => $source['recommendation']['originalTargetImplementationDate'],
            'transferActorId' => $intake->transferred_by,
            'transferredAt' => $intake->transferred_at?->toIso8601String(),
            'resultingCaseStatus' => $case->status_code,
            'sourceSummary' => [
                'engagementCode' => $source['engagement']['code'],
                'reportCode' => $source['report']['code'],
                'reportVersionNumber' => $source['report']['versionNumber'],
                'findingCode' => $source['finding']['code'],
                'recommendationCode' => $source['recommendation']['code'],
                'recommendation' => $source['recommendation']['wording'],
            ],
        ];
    }

    private function recordCreation(
        Request $request,
        CmsRecommendation $intake,
        CmsRecommendationCase $case,
        array $metadata,
    ): void {
        $newValues = [
            'cmsRecommendationId' => $intake->id,
            'cmsRecommendationCaseId' => $case->id,
            'status' => $intake->status,
            'caseStatus' => $case->status_code,
            'transferKey' => $intake->transfer_key,
        ];
        $logMetadata = [
            'module' => 'CMS',
            'recordType' => CmsRecommendation::class,
            'recordId' => $intake->id,
            'path' => null,
            ...$metadata,
        ];

        ActivityRecorder::record(
            $request,
            self::ACTION_INTAKE_CREATED,
            "CMS recommendation intake created: {$intake->recommendation_code}",
            oldValues: null,
            newValues: $newValues,
            metadata: $logMetadata,
        );
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => self::ACTION_INTAKE_CREATED,
            'auditable_type' => CmsRecommendation::class,
            'auditable_id' => $intake->id,
            'old_values' => null,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $logMetadata,
        ]);
    }
}
