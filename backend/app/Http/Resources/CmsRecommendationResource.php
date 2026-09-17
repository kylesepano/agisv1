<?php

namespace App\Http\Resources;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes a CMS registry row without exposing source files or private paths. */
class CmsRecommendationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $target = $this->effective_target_implementation_date;

        return [
            'id' => $this->id,
            'cmsRecommendationCode' => sprintf('CMS-REC-%06d', $this->id),
            'status' => $this->status_code,
            'activeCycleNumber' => (int) ($this->active_cycle_number ?: 1),
            'reopeningCount' => (int) ($this->reopening_count ?: 0),
            'lastReopenedAt' => $this->last_reopened_at?->toISOString(),
            'currentlyReopened' => (int) ($this->reopening_count ?: 0) > 0
                && ! in_array($this->status_code, ['CLOSED', 'ACCEPTED_RISK', 'NO_LONGER_APPLICABLE', 'CANCELLED'], true),
            'terminalSource' => $this->last_reopening_decision_id ? 'REOPENED' : ($this->status_code === 'CLOSED' ? 'CLOSURE' : (in_array($this->status_code, ['ACCEPTED_RISK', 'NO_LONGER_APPLICABLE'], true) ? 'DISPOSITION' : null)),
            'openedAt' => $this->opened_at?->toISOString(),
            'transferredAt' => $this->recommendation?->transferred_at?->toISOString(),
            'effectiveTargetDate' => $target?->toDateString(),
            'isOverdue' => $target !== null
                && $target->lt(CarbonImmutable::today())
                && ! in_array($this->status_code, [
                    'CLOSED', 'ACCEPTED_RISK', 'NO_LONGER_APPLICABLE', 'CANCELLED',
                ], true),
            'lockVersion' => $this->lock_version,
            'recommendationCode' => $this->recommendation?->recommendation_code,
            'recommendation' => data_get(
                $this->recommendation?->source_snapshot,
                'recommendation.wording',
            ),
            'finding' => [
                'code' => data_get($this->recommendation?->source_snapshot, 'finding.code'),
                'title' => data_get($this->recommendation?->source_snapshot, 'finding.title'),
            ],
            'engagement' => [
                'code' => data_get($this->recommendation?->source_snapshot, 'engagement.code'),
                'title' => data_get($this->recommendation?->source_snapshot, 'engagement.title'),
            ],
            'finalReportNumber' => $this->recommendation?->report_code_snapshot,
            'risk' => [
                'id' => $this->recommendation?->risk_rating_id,
                'code' => $this->recommendation?->risk_code_snapshot,
                'label' => $this->recommendation?->risk_label_snapshot,
            ],
            'confidentiality' => [
                'id' => $this->recommendation?->confidentiality_level_id,
                'code' => $this->recommendation?->confidentiality_code_snapshot,
                'label' => $this->recommendation?->confidentiality_label_snapshot,
            ],
            'responsibleOffice' => $this->whenLoaded(
                'leadResponsibleOffice',
                fn () => $this->leadResponsibleOffice?->only(['id', 'code', 'name', 'acronym']),
            ),
            'currentMonitor' => $this->whenLoaded(
                'currentAssignment',
                fn () => $this->currentAssignment
                    ? new CmsRecommendationAssignmentResource($this->currentAssignment)
                    : null,
            ),
        ];
    }
}
