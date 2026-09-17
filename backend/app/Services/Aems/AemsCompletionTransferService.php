<?php

namespace App\Services;

use App\Contracts\Aems\CmsRecommendationGateway;
use App\Contracts\Aems\ResourcePlanningGateway;
use App\Integrations\Aems\ArmisResourcePlanningGateway;
use App\Integrations\Aems\ConfigurableResourcePlanningGateway;
use App\Models\AemsCompletionTransferException;
use App\Models\AemsCompletionTransferManifest;
use App\Models\AemsEffortReconciliation;
use App\Models\AuditEngagement;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pins AEMS completion transfer and resource-effort reconciliation snapshots.
 * CMS remains the owner of operational monitoring after the transfer boundary.
 */
class AemsCompletionTransferService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly CmsRecommendationGateway $cms,
        private readonly ResourcePlanningGateway $resources,
        private readonly ArmisResourcePlanningGateway $armis,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.completion-transfer.view');
        $manifest = AemsCompletionTransferManifest::query()
            ->where('audit_engagement_id', $engagement->id)
            ->with(['reportVersion', 'exceptions.recommendation', 'generator', 'reconciler', 'reviewer', 'approver'])
            ->latest('id')->first();
        $effort = AemsEffortReconciliation::query()
            ->where('audit_engagement_id', $engagement->id)
            ->with(['generator', 'reviewer', 'approver'])
            ->latest('version_number')->first();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'status' => $engagement->status,
                'plannedPersonDays' => (float) $engagement->planned_person_days,
                'actualPersonDays' => (float) $engagement->actual_person_days,
            ],
            'manifest' => $manifest ? $this->manifestData($manifest) : null,
            'effortReconciliation' => $effort ? $this->effortData($effort) : null,
            'provider' => $this->providerStatus($engagement),
            'permittedActions' => [
                'reconcile' => $request->user()->hasPermission('aems.completion-transfer.reconcile'),
                'approve' => $request->user()->hasPermission('aems.completion-transfer.approve'),
            ],
        ];
    }

    public function reconcile(Request $request, AuditEngagement $engagement): array
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.completion-transfer.reconcile');

        $result = DB::transaction(function () use ($request, $engagement): array {
            $report = AuditReport::query()
                ->where('audit_engagement_id', $engagement->id)
                ->where('report_stage', 'FINAL_REPORT')
                ->where('status', 'ISSUED')
                ->with('currentVersion.findings.recommendations')
                ->first();
            $manifest = $report?->currentVersion
                ? $this->reconcileManifest($request, $engagement, $report, $report->currentVersion)
                : null;
            $effort = $this->reconcileEffort($request, $engagement);
            return [
                'manifest' => $manifest ? $this->manifestData($manifest->fresh(['reportVersion', 'exceptions.recommendation', 'generator', 'reconciler', 'reviewer', 'approver'])) : null,
                'effortReconciliation' => $this->effortData($effort->fresh(['generator', 'reviewer', 'approver'])),
                'provider' => $this->providerStatus($engagement),
            ];
        });
        $this->notifications->completionTransfer($request, $engagement, 'RECONCILED', $result);
        return $result;
    }

    public function approve(Request $request, AuditEngagement $engagement, string $type, int $id, int $lockVersion, string $comment): array
    {
        $this->access->authorizeEngagementAction($request->user(), $engagement, 'aems.completion-transfer.approve');
        abort_if(
            (int) $request->user()->id === $this->generatedBy($type, $id)
                && ! $this->access->mayUseSingleCiasHeadReviewException($request->user(), 'aems.completion-transfer.approve'),
            403,
            'The reconciliation generator cannot approve the same snapshot.',
        );

        if ($type === 'MANIFEST') {
            $record = AemsCompletionTransferManifest::query()->lockForUpdate()->findOrFail($id);
            abort_unless((int) $record->audit_engagement_id === (int) $engagement->id, 404);
            abort_unless($record->lock_version === $lockVersion, 422, 'The transfer manifest changed. Refresh before approval.');
            abort_unless($record->status === 'RECONCILED' && $record->exception_count === 0, 422, 'Only a reconciled manifest without exceptions can be approved.');
            $record->forceFill(['status' => 'APPROVED', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'approved_by' => $request->user()->id, 'approved_at' => now(), 'review_comment' => trim($comment), 'reconciliation_comment' => trim($comment), 'lock_version' => $record->lock_version + 1])->save();
            $this->support->audit($request, 'aems.completion_transfer.manifest_approved', $engagement, null, $this->manifestData($record), ['subjectType' => 'AEMS_TRANSFER_MANIFEST', 'subjectId' => $record->id]);
            $result = ['manifest' => $this->manifestData($record->fresh(['reportVersion', 'exceptions.recommendation', 'generator', 'reconciler', 'reviewer', 'approver']))];
            $this->notifications->completionTransfer($request, $engagement, 'MANIFEST_APPROVED', $result);
            return $result;
        }

        $record = AemsEffortReconciliation::query()->lockForUpdate()->findOrFail($id);
        abort_unless((int) $record->audit_engagement_id === (int) $engagement->id, 404);
        abort_unless($record->lock_version === $lockVersion, 422, 'The effort reconciliation changed. Refresh before approval.');
        abort_unless($record->status === 'RECONCILED', 422, 'Only a reconciled effort snapshot can be approved.');
        $record->forceFill(['status' => 'APPROVED', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'approved_by' => $request->user()->id, 'approved_at' => now(), 'review_comment' => trim($comment), 'lock_version' => $record->lock_version + 1])->save();
        $this->support->audit($request, 'aems.completion_transfer.effort_approved', $engagement, null, $this->effortData($record), ['subjectType' => 'AEMS_EFFORT_RECONCILIATION', 'subjectId' => $record->id]);
        $result = ['effortReconciliation' => $this->effortData($record->fresh(['generator', 'reviewer', 'approver']))];
        $this->notifications->completionTransfer($request, $engagement, 'EFFORT_APPROVED', $result);
        return $result;
    }

    /** @return array<string, mixed> */
    public function closureGate(AuditEngagement $engagement): array
    {
        $recommendationCount = $engagement->findings()->where('is_current_revision', true)->with('recommendations')->get()->flatMap->recommendations->count();
        $currentIssuedVersionId = AuditReport::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('report_stage', 'FINAL_REPORT')
            ->where('status', 'ISSUED')
            ->value('current_version_id');
        $manifest = AemsCompletionTransferManifest::query()->where('audit_engagement_id', $engagement->id)->latest('id')->first();
        $manifestReady = $recommendationCount === 0 || ($manifest
            && (int) $manifest->audit_report_version_id === (int) $currentIssuedVersionId
            && in_array($manifest->status, ['RECONCILED', 'APPROVED'], true)
            && $manifest->exception_count === 0);
        $effort = AemsEffortReconciliation::query()->where('audit_engagement_id', $engagement->id)->latest('version_number')->first();
        $provider = $this->providerStatus($engagement);
        // ARMIS is the sole operational effort provider. An AEMS-local actual
        // value cannot bypass the immutable ARMIS reconciliation gate.
        $effortReady = $effort && in_array($effort->status, ['RECONCILED', 'APPROVED'], true);

        return [
            'manifestReady' => (bool) $manifestReady,
            'manifestStatus' => $manifest?->status,
            'openExceptions' => $manifest?->exception_count ?? 0,
            'effortReady' => (bool) $effortReady,
            'effortStatus' => $effort?->status,
            'providerMode' => $provider['mode'],
        ];
    }

    private function reconcileManifest(Request $request, AuditEngagement $engagement, AuditReport $report, AuditReportVersion $version): AemsCompletionTransferManifest
    {
        $manifest = AemsCompletionTransferManifest::query()->firstOrCreate(
            ['audit_engagement_id' => $engagement->id, 'audit_report_version_id' => $version->id],
            ['audit_report_id' => $report->id, 'manifest_code' => $engagement->engagement_code.'-CMS-MANIFEST-'.$version->version_number, 'generated_by' => $request->user()->id, 'generated_at' => now()],
        );
        abort_if($manifest->status === 'APPROVED', 422, 'The approved CMS transfer manifest is immutable.');
        $items = [];
        $exceptions = [];
        $recommendations = $version->findings->flatMap->recommendations;
        foreach ($recommendations as $recommendation) {
            if ($recommendation->status === 'FINALIZED') {
                $cms = $this->cms->transfer($recommendation, $engagement, $report, $version, $request);
                $items[] = ['recommendationId' => $recommendation->id, 'recommendationCode' => $recommendation->recommendation_code, 'outcome' => 'TRANSFERRED', 'cmsRecommendationId' => $cms->id, 'transferKey' => $cms->transfer_key];
            } elseif ($recommendation->status === 'TRANSFERRED' && $recommendation->cms_recommendation_id) {
                $items[] = ['recommendationId' => $recommendation->id, 'recommendationCode' => $recommendation->recommendation_code, 'outcome' => 'TRANSFERRED', 'cmsRecommendationId' => $recommendation->cms_recommendation_id, 'transferKey' => $recommendation->cms_transfer_key];
            } elseif ($recommendation->status === 'EXCLUDED' && filled($recommendation->cms_exclusion_reason)) {
                $items[] = ['recommendationId' => $recommendation->id, 'recommendationCode' => $recommendation->recommendation_code, 'outcome' => 'EXCLUDED', 'reason' => $recommendation->cms_exclusion_reason];
            } else {
                $message = 'Recommendation is not transferred or formally excluded.';
                $exceptions[] = ['recommendation' => $recommendation, 'code' => 'TRANSFER_INCOMPLETE', 'message' => $message];
            }
        }
        $manifest->exceptions()->delete();
        foreach ($exceptions as $exception) {
            $manifest->exceptions()->create(['audit_recommendation_id' => $exception['recommendation']->id, 'exception_code' => $exception['code'], 'message' => $exception['message'], 'created_by' => $request->user()->id]);
        }
        $manifest->forceFill([
            'status' => $exceptions === [] ? 'RECONCILED' : 'EXCEPTION',
            'expected_count' => $recommendations->count(),
            'transferred_count' => collect($items)->where('outcome', 'TRANSFERRED')->count(),
            'excluded_count' => collect($items)->where('outcome', 'EXCLUDED')->count(),
            'exception_count' => count($exceptions),
            'manifest_snapshot_json' => ['reportId' => $report->id, 'reportVersionId' => $version->id, 'documentVersionId' => $version->document_version_id, 'checksumSha256' => $version->checksum_sha256, 'items' => $items],
            'reconciled_by' => $request->user()->id,
            'reconciled_at' => now(),
            'lock_version' => $manifest->lock_version + 1,
        ])->save();
        $this->support->event($request, $engagement, 'aems.completion_transfer.manifest_reconciled', 'DRAFT', $manifest->status, null, $this->manifestData($manifest), 'CMS transfer manifest reconciled.', 'AEMS_TRANSFER_MANIFEST', $manifest->id, 1, $manifest->manifest_code, null, [$version->document_version_id]);
        return $manifest;
    }

    private function reconcileEffort(Request $request, AuditEngagement $engagement): AemsEffortReconciliation
    {
        $provider = $this->providerStatus($engagement);
        $mode = ConfigurableResourcePlanningGateway::ARMIS_AUTHORITATIVE;
        $aemsActual = round((float) $engagement->actual_person_days, 2);
        $providerActual = round((float) $this->armis->engagementActualPersonDays($engagement), 2);
        $next = ((int) AemsEffortReconciliation::query()->where('audit_engagement_id', $engagement->id)->max('version_number')) + 1;
        $status = $providerActual > 0 ? 'RECONCILED' : 'EXCEPTION';
        $reconciliation = AemsEffortReconciliation::query()->create([
            'audit_engagement_id' => $engagement->id,
            'version_number' => $next,
            'provider_mode' => $mode,
            'status' => $status,
            'planned_person_days' => (float) $engagement->planned_person_days,
            'aems_actual_person_days' => $aemsActual,
            'provider_actual_person_days' => $providerActual,
            'variance_person_days' => round($providerActual - $aemsActual, 2),
            'source_snapshot_json' => ['provider' => $provider, 'engagementId' => $engagement->id],
            'generated_by' => $request->user()->id,
            'generated_at' => now(),
        ]);
        $this->support->audit($request, 'aems.completion_transfer.effort_reconciled', $engagement, null, $this->effortData($reconciliation), ['subjectType' => 'AEMS_EFFORT_RECONCILIATION', 'subjectId' => $reconciliation->id]);
        $this->support->event($request, $engagement, 'aems.completion_transfer.effort_reconciled', 'DRAFT', $reconciliation->status, null, $this->effortData($reconciliation), 'ARMIS effort reconciliation generated.', 'AEMS_EFFORT_RECONCILIATION', $reconciliation->id, $reconciliation->version_number, $engagement->engagement_code, null, []);
        return $reconciliation;
    }

    /** @return array<string, mixed> */
    private function providerStatus(AuditEngagement $engagement): array
    {
        return $this->resources->status();
    }

    private function generatedBy(string $type, int $id): ?int
    {
        return $type === 'MANIFEST'
            ? AemsCompletionTransferManifest::query()->whereKey($id)->value('generated_by')
            : AemsEffortReconciliation::query()->whereKey($id)->value('generated_by');
    }

    /** @return array<string, mixed> */
    private function manifestData(AemsCompletionTransferManifest $manifest): array
    {
        return ['id' => $manifest->id, 'manifestCode' => $manifest->manifest_code, 'status' => $manifest->status, 'reportId' => $manifest->audit_report_id, 'reportVersionId' => $manifest->audit_report_version_id, 'expectedCount' => $manifest->expected_count, 'transferredCount' => $manifest->transferred_count, 'excludedCount' => $manifest->excluded_count, 'exceptionCount' => $manifest->exception_count, 'snapshot' => $manifest->manifest_snapshot_json, 'generatedBy' => $manifest->generated_by, 'generatedAt' => $manifest->generated_at?->toISOString(), 'reconciledBy' => $manifest->reconciled_by, 'reconciledAt' => $manifest->reconciled_at?->toISOString(), 'reviewedBy' => $manifest->reviewed_by, 'reviewedAt' => $manifest->reviewed_at?->toISOString(), 'approvedBy' => $manifest->approved_by, 'approvedAt' => $manifest->approved_at?->toISOString(), 'lockVersion' => $manifest->lock_version, 'exceptions' => $manifest->exceptions->map(fn (AemsCompletionTransferException $exception): array => ['id' => $exception->id, 'recommendationId' => $exception->audit_recommendation_id, 'code' => $exception->exception_code, 'message' => $exception->message, 'status' => $exception->status])->values()->all()];
    }

    /** @return array<string, mixed> */
    private function effortData(AemsEffortReconciliation $effort): array
    {
        return ['id' => $effort->id, 'versionNumber' => $effort->version_number, 'providerMode' => $effort->provider_mode, 'status' => $effort->status, 'plannedPersonDays' => (float) $effort->planned_person_days, 'aemsActualPersonDays' => (float) $effort->aems_actual_person_days, 'providerActualPersonDays' => $effort->provider_actual_person_days === null ? null : (float) $effort->provider_actual_person_days, 'variancePersonDays' => (float) $effort->variance_person_days, 'sourceSnapshot' => $effort->source_snapshot_json, 'generatedBy' => $effort->generated_by, 'generatedAt' => $effort->generated_at?->toISOString(), 'approvedBy' => $effort->approved_by, 'approvedAt' => $effort->approved_at?->toISOString(), 'lockVersion' => $effort->lock_version];
    }
}
