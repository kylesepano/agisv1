<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes prioritization runs, ranked items, decisions, and source assessments.
 */
class IapPrioritizationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'runCode' => $this->run_code,
            'name' => $this->name,
            'riskPeriodId' => $this->risk_period_id,
            'methodology' => $this->methodology,
            'status' => $this->status,
            'lockVersion' => $this->lock_version,
            'isActive' => $this->is_active,
            'isArchived' => $this->trashed(),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'finalizedAt' => $this->finalized_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];

        if (isset($this->items_count)) {
            $data['itemCount'] = (int) $this->items_count;
        }
        foreach (['creator', 'submitter', 'finalizer'] as $relation) {
            if ($this->relationLoaded($relation)) {
                $data[$relation] = $this->user($this->{$relation});
            }
        }
        if ($this->relationLoaded('riskPeriod')) {
            $data['riskPeriod'] = $this->riskPeriod ? [
                'id' => $this->riskPeriod->id,
                'periodCode' => $this->riskPeriod->period_code,
                'name' => $this->riskPeriod->name,
                'assessmentYear' => $this->riskPeriod->assessment_year,
                'status' => $this->riskPeriod->status,
            ] : null;
        }
        if ($this->relationLoaded('items')) {
            $data['items'] = $this->items->map(fn ($item) => [
                'id' => $item->id,
                'riskAssessmentId' => $item->risk_assessment_id,
                'auditUniverseItemId' => $item->audit_universe_item_id,
                'subjectCode' => $item->subject_code,
                'subjectName' => $item->subject_name,
                'officeCode' => $item->office_code,
                'officeName' => $item->office_name,
                'auditAreaCode' => $item->audit_area_code,
                'auditAreaName' => $item->audit_area_name,
                'inherentRiskScore' => (float) $item->inherent_risk_score,
                'controlEffectivenessPercent' => (float) $item->control_effectiveness_percent,
                'residualRiskScore' => (float) $item->residual_risk_score,
                'riskLevelCode' => $item->risk_level_code,
                'riskLevelLabel' => $item->risk_level_label,
                'priorityScore' => (float) $item->priority_score,
                'systemRank' => $item->system_rank,
                'finalRank' => $item->final_rank,
                'recommendedDecision' => $item->recommended_decision,
                'decision' => $item->decision,
                'decisionReason' => $item->decision_reason,
                'isManualOverride' => $item->is_manual_override,
                'overrideReason' => $item->override_reason,
                'lockVersion' => $item->lock_version,
            ])->values();
        }
        if ($this->relationLoaded('events')) {
            $data['events'] = $this->events->map(fn ($event) => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'actor' => $this->user($event->actor),
                'comment' => $event->comment,
                'createdAt' => $event->created_at?->toISOString(),
            ])->values();
        }

        return $data;
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
}
