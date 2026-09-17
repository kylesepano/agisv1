<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsReopeningDecisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'decisionCode' => $this->decision_code, 'decidedAt' => $this->decided_at?->toISOString(), 'decisionComment' => $this->decision_comment, 'overrideReason' => $this->override_reason, 'sourceTerminalStatus' => $this->source_terminal_status, 'approvedDestinationStatus' => $this->approved_destination_status, 'previousActiveCycleNumber' => $this->previous_active_cycle_number, 'newActiveCycleNumber' => $this->new_active_cycle_number, 'existingActionPlanRetained' => (bool) $this->existing_action_plan_retained, 'retainedActionPlanVersionId' => $this->retained_action_plan_version_id, 'newActionPlanRequired' => (bool) $this->new_action_plan_required, 'assignmentFollowUpRequired' => (bool) $this->assignment_follow_up_required, 'targetDateFollowUpRequired' => (bool) $this->target_date_follow_up_required, 'reopeningEffectiveDate' => $this->reopening_effective_date?->toDateString(), 'finalSnapshot' => $this->final_snapshot, 'decidedBy' => $this->decider?->only(['id', 'employee_id', 'name', 'initials'])];
    }
}
