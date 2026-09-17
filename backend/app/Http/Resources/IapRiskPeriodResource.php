<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes risk periods, configurable criteria, and assessment aggregates.
 */
class IapRiskPeriodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'periodCode' => $this->period_code,
            'name' => $this->name,
            'assessmentYear' => $this->assessment_year,
            'startDate' => $this->start_date?->toDateString(),
            'endDate' => $this->end_date?->toDateString(),
            'instructions' => $this->instructions,
            'status' => $this->status,
            'lockVersion' => $this->lock_version,
            'isActive' => $this->is_active,
            'isArchived' => $this->trashed(),
            'openedAt' => $this->opened_at?->toISOString(),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'validatedAt' => $this->validated_at?->toISOString(),
            'lockedAt' => $this->locked_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];

        if (isset($this->assessments_count)) {
            $data['assessmentCount'] = (int) $this->assessments_count;
        }
        foreach (['creator', 'submitter', 'validator'] as $relation) {
            if ($this->relationLoaded($relation)) {
                $data[$relation] = $this->user($this->{$relation});
            }
        }
        if ($this->relationLoaded('criteria')) {
            $data['criteria'] = $this->criteria->map(fn ($criterion) => [
                'id' => $criterion->id,
                'criterionId' => $criterion->criterion_id,
                'code' => $criterion->criterion?->code,
                'label' => $criterion->criterion?->label,
                'description' => $criterion->criterion?->description,
                'weight' => (float) $criterion->weight,
                'displayOrder' => $criterion->display_order,
            ])->values();
        }
        if ($this->relationLoaded('assessments')) {
            $data['assessments'] = $this->assessments
                ->map(fn ($assessment) => $this->assessment($assessment))
                ->values();
        }
        if ($this->relationLoaded('events')) {
            $data['events'] = $this->events->map(fn ($event) => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'comment' => $event->comment,
                'actor' => $this->user($event->actor),
                'createdAt' => $event->created_at?->toISOString(),
            ])->values();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function assessment(mixed $assessment): array
    {
        return [
            'id' => $assessment->id,
            'periodId' => $assessment->period_id,
            'auditUniverseItemId' => $assessment->audit_universe_item_id,
            'auditUniverseItem' => $assessment->auditUniverseItem ? [
                'id' => $assessment->auditUniverseItem->id,
                'subjectCode' => $assessment->auditUniverseItem->subject_code,
                'name' => $assessment->auditUniverseItem->name,
                'responsibleOffice' => $assessment->auditUniverseItem->responsibleOffice
                    ? $assessment->auditUniverseItem->responsibleOffice->only(['id', 'code', 'name'])
                    : null,
                'primaryAuditArea' => $assessment->auditUniverseItem->primaryAuditArea
                    ? $assessment->auditUniverseItem->primaryAuditArea->only(['id', 'code', 'name'])
                    : null,
            ] : null,
            'assessor' => $this->user($assessment->assessor),
            'assessmentDate' => $assessment->assessment_date?->toDateString(),
            'controlEffectivenessPercent' => (float) $assessment->control_effectiveness_percent,
            'inherentRiskScore' => (float) $assessment->inherent_risk_score,
            'residualRiskScore' => (float) $assessment->residual_risk_score,
            'inherentRiskLevel' => $this->item($assessment->inherentRiskLevel),
            'residualRiskLevel' => $this->item($assessment->residualRiskLevel),
            'controlEffectivenessNotes' => $assessment->control_effectiveness_notes,
            'justification' => $assessment->justification,
            'evidenceSummary' => $assessment->evidence_summary,
            'status' => $assessment->status,
            'validationComment' => $assessment->validation_comment,
            'validatedAt' => $assessment->validated_at?->toISOString(),
            'lockVersion' => $assessment->lock_version,
            'isArchived' => $assessment->trashed(),
            'scores' => $assessment->scores->map(fn ($score) => [
                'id' => $score->id,
                'criterionId' => $score->criterion_id,
                'criterion' => $this->item($score->criterion),
                'weight' => (float) $score->criterion_weight,
                'rating' => (float) $score->rating,
                'weightedScore' => (float) $score->weighted_score,
                'comment' => $score->comment,
            ])->values(),
            'evidence' => $assessment->evidence->map(fn ($evidence) => [
                'id' => $evidence->id,
                'fileName' => $evidence->original_file_name,
                'mimeType' => $evidence->mime_type,
                'fileExtension' => $evidence->file_extension,
                'fileSize' => $evidence->file_size,
                'uploadedBy' => $this->user($evidence->uploader),
                'createdAt' => $evidence->created_at?->toISOString(),
            ])->values(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function item(mixed $item): ?array
    {
        return $item ? [
            'id' => $item->id,
            'code' => $item->code,
            'label' => $item->label,
        ] : null;
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
