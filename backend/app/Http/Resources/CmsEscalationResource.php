<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsEscalationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $e = $this->resource;
        $notice = $e->currentNotice;
        $response = $e->response;

        return [
            'id' => $e->id,
            'caseId' => $e->cms_recommendation_case_id,
            'displayCode' => $e->display_code,
            'sequence' => $e->escalation_sequence,
            'primaryTriggerCode' => $e->primary_trigger_code,
            'triggerSnapshot' => $e->trigger_snapshot,
            'sourceEffectiveTargetDate' => $e->source_effective_target_date?->toDateString(),
            'sourceCaseStatus' => $e->source_case_status,
            'operationalStatus' => $e->operational_status_code,
            'resolvedAt' => $e->resolved_at?->toISOString(),
            'lockVersion' => $e->lock_version,
            'currentNotice' => $notice ? new CmsEscalationNoticeVersionResource($notice) : null,
            'issuedNotice' => $e->issuedNotice ? new CmsEscalationNoticeVersionResource($e->issuedNotice) : null,
            'response' => $response ? new CmsEscalationResponseResource($response) : null,
            'resolution' => $e->resolution ? ['id' => $e->resolution->id, 'resolutionCode' => $e->resolution->resolution_code, 'summary' => $e->resolution->resolution_summary, 'basisForResolution' => $e->resolution->basis_for_resolution, 'followUpRequirements' => $e->resolution->follow_up_requirements, 'resolvedAt' => $e->resolution->resolved_at?->toISOString(), 'recommendationClosed' => false] : null,
            'noticeVersions' => CmsEscalationNoticeVersionResource::collection($e->relationLoaded('noticeVersions') ? $e->noticeVersions : collect()),
            'caseContext' => $e->case ? ['id' => $e->case->id, 'status' => $e->case->status_code, 'originalTargetDate' => $e->case->recommendation?->original_target_implementation_date?->toDateString(), 'effectiveTargetDate' => $e->case->effective_target_implementation_date?->toDateString(), 'responsibleOffice' => $e->case->leadResponsibleOffice?->only(['id', 'code', 'name', 'acronym'])] : null,
            'availableActions' => $e->available_actions ?? [],
        ];
    }
}
