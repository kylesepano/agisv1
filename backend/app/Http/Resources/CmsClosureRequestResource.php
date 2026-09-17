<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CmsClosureRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        $v = $this->currentVersion;

        return ['id' => $this->id, 'displayCode' => $this->display_code, 'recommendationCaseId' => $this->cms_recommendation_case_id, 'requestSequence' => $this->request_sequence, 'initiatorTypeCode' => $this->initiator_type_code, 'createdBy' => $this->created_by, 'currentVersion' => $v ? new CmsClosureRequestVersionResource($v) : null, 'resolvedVersionId' => $this->resolved_version_id, 'resolvedAt' => $this->resolved_at, 'lockVersion' => $this->lock_version, 'availableActions' => $this->available_actions ?? [], 'isResolved' => (bool) $this->resolved_at];
    }
}
