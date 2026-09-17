<?php

namespace App\Services;

use App\Models\IapBaicsIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delivers BAICS-to-IAP integration workflow notifications through Core.
 *
 * Recipients are limited to the decision participants (preparer, independent
 * reviewer, and approving authority). Core still applies the recipient's
 * notification permission and preference filters before delivery.
 */
class IapBaicsIntegrationNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function saved(Request $request, IapBaicsIntegration $integration, bool $created): void
    {
        $event = $created ? 'DRAFTED' : 'UPDATED';
        $this->deliver($request, $integration, $event, null, $integration->status);
    }

    public function transitioned(
        Request $request,
        IapBaicsIntegration $integration,
        string $action,
        ?string $oldStatus,
    ): void {
        $this->deliver($request, $integration, strtoupper($action), $oldStatus, $integration->status);
    }

    private function deliver(
        Request $request,
        IapBaicsIntegration $integration,
        string $event,
        ?string $oldStatus,
        ?string $newStatus,
    ): void {
        $recipientIds = $this->recipients($integration, $event)
            ->reject(fn ($id): bool => (int) $id === (int) $request->user()->id)
            ->filter()
            ->unique()
            ->values();

        if ($recipientIds->isEmpty()) {
            return;
        }

        $integrationId = (int) $integration->id;
        $assessmentId = (int) $integration->assessment_id;
        $code = (string) $integration->integration_code;
        $consumer = (string) $integration->consumer_type.' #'.(int) $integration->consumer_id;
        $statusLabel = strtolower(str_replace('_', ' ', (string) $newStatus));
        $actionLabel = strtolower(str_replace('_', ' ', $event));
        $priority = in_array($event, ['SUBMIT', 'RETURN', 'APPROVE', 'RETIRE'], true)
            ? 'HIGH'
            : 'NORMAL';

        DB::afterCommit(function () use (
            $recipientIds,
            $request,
            $integration,
            $integrationId,
            $assessmentId,
            $code,
            $consumer,
            $statusLabel,
            $actionLabel,
            $event,
            $oldStatus,
            $newStatus,
            $priority,
        ): void {
            $this->notifications->send($recipientIds, [
                'actorId' => $request->user()->id,
                'type' => 'IAP_BAICS_INTEGRATION_'.$event,
                'category' => 'WORKFLOW',
                'priority' => $priority,
                'moduleCode' => 'IAP',
                'title' => "{$code}: BAICS integration {$actionLabel}",
                'message' => "The {$consumer} BAICS integration decision is now {$statusLabel}.",
                'actionUrl' => "/internal-audit-planning/baics/integration?assessmentId={$assessmentId}&integrationId={$integrationId}",
                'actionLabel' => 'Open BAICS integration',
                'subjectType' => IapBaicsIntegration::class,
                'subjectId' => $integrationId,
                'subjectCode' => $code,
                'dedupeKey' => "baics:iap-integration:{$integrationId}:{$integration->lock_version}:{$event}",
                'metadata' => [
                    'integrationId' => $integrationId,
                    'assessmentId' => $assessmentId,
                    'consumerType' => $integration->consumer_type,
                    'consumerId' => (int) $integration->consumer_id,
                    'decisionType' => $integration->decision_type,
                    'event' => $event,
                    'oldStatus' => $oldStatus,
                    'newStatus' => $newStatus,
                    'versionNumber' => (int) $integration->version_number,
                ],
            ]);
        });
    }

    /** @return Collection<int, int|null> */
    private function recipients(IapBaicsIntegration $integration, string $event): Collection
    {
        $participants = match ($event) {
            'DRAFTED', 'UPDATED', 'SUBMIT' => collect([$integration->reviewer_id, $integration->authority_user_id]),
            'REVIEW' => collect([$integration->created_by, $integration->authority_user_id]),
            'RETURN' => collect([$integration->created_by, $integration->reviewer_id]),
            'APPROVE' => collect([$integration->created_by, $integration->reviewer_id]),
            'RETIRE' => collect([$integration->created_by, $integration->reviewer_id, $integration->authority_user_id]),
            default => collect([$integration->created_by, $integration->reviewer_id, $integration->authority_user_id]),
        };

        return $participants->map(fn ($id): ?int => $id === null ? null : (int) $id);
    }
}
