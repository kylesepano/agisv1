<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsReopeningRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $version = $this->currentVersion;

        return ['id' => $this->id, 'displayCode' => $this->display_code, 'requestSequence' => $this->request_sequence, 'initiatorTypeCode' => $this->initiator_type_code, 'sourceTerminalStatus' => $this->source_terminal_status, 'sourceClosureDecisionId' => $this->source_closure_decision_id, 'sourceDispositionDecisionId' => $this->source_disposition_decision_id, 'createdBy' => $this->creator?->only(['id', 'employee_id', 'name', 'initials']), 'currentVersion' => $version ? new CmsReopeningRequestVersionResource($version) : null, 'resolvedVersionId' => $this->resolved_version_id, 'resolvedAt' => $this->resolved_at?->toISOString(), 'lockVersion' => $this->lock_version, 'availableActions' => $this->getAttribute('available_actions') ?? [], 'isResolved' => (bool) $this->resolved_at];
    }
}
