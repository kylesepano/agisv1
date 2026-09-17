<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsClosureCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $case = $this->case;
        return [
            'id' => $this->id,
            'statusCode' => $this->status_code,
            'detectedAt' => $this->detected_at?->toISOString(),
            'readiness' => $this->readiness_snapshot,
            'closureRequestId' => $this->closure_request_id,
            'reviewNote' => $this->review_note,
            'case' => $case ? ['id' => $case->id, 'code' => sprintf('CMS-REC-%06d', $case->id), 'status' => $case->status_code, 'responsibleOffice' => $case->leadResponsibleOffice?->only(['id', 'code', 'name'])] : null,
            'reviewer' => $this->reviewer?->only(['id', 'employee_id', 'name']),
        ];
    }
}
