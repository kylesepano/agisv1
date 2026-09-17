<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsValidationItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scopeCode' => $this->scope_code,
            'actionPlanMilestoneId' => $this->cms_action_plan_milestone_id,
            'milestoneProgressId' => $this->cms_milestone_progress_id,
            'sequenceNumber' => $this->sequence_number,
            'criterion' => $this->criterion,
            'procedurePerformed' => $this->procedure_performed,
            'populationOrSource' => $this->population_or_source,
            'sampleDescription' => $this->sample_description,
            'resultSummary' => $this->result_summary,
            'exceptionSummary' => $this->exception_summary,
            'itemConclusionCode' => $this->item_conclusion_code,
            'validatedMilestonePercentage' => $this->validated_milestone_percentage,
            'followUpRequired' => $this->follow_up_required,
            'displayOrder' => $this->display_order,
            'milestone' => $this->whenLoaded('actionPlanMilestone', fn () => [
                'id' => $this->actionPlanMilestone->id,
                'sequenceNumber' => $this->actionPlanMilestone->sequence_number,
                'title' => $this->actionPlanMilestone->title,
                'expectedOutput' => $this->actionPlanMilestone->expected_output,
                'successIndicator' => $this->actionPlanMilestone->success_indicator,
                'verificationMethod' => $this->actionPlanMilestone->verification_method,
                'weightPercentage' => $this->actionPlanMilestone->weight_percentage,
            ]),
        ];
    }
}
