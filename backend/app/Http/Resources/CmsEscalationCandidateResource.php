<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsEscalationCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $case = $this->case;
        return [
            'id' => $this->id,
            'statusCode' => $this->status_code,
            'triggerCode' => $this->trigger_code,
            'severityCode' => $this->severity_code,
            'reason' => $this->reason,
            'detectedAt' => $this->detected_at?->toISOString(),
            'triggerSnapshot' => $this->trigger_snapshot,
            'escalationId' => $this->escalation_id,
            'reviewNote' => $this->review_note,
            'case' => $case ? ['id' => $case->id, 'code' => sprintf('CMS-REC-%06d', $case->id), 'status' => $case->status_code, 'responsibleOffice' => $case->leadResponsibleOffice?->only(['id', 'code', 'name'])] : null,
            'reviewer' => $this->reviewer?->only(['id', 'employee_id', 'name']),
        ];
    }
}
