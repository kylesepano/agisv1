<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsActionPlanMilestoneResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequenceNumber' => $this->sequence_number,
            'title' => $this->title,
            'description' => $this->description,
            'expectedOutput' => $this->expected_output,
            'successIndicator' => $this->success_indicator,
            'verificationMethod' => $this->verification_method,
            'responsibleOffice' => $this->whenLoaded(
                'responsibleOffice',
                fn () => $this->responsibleOffice?->only(['id', 'code', 'name', 'acronym']),
            ),
            'responsibleUser' => $this->whenLoaded(
                'responsibleUser',
                fn () => $this->safeUser($this->responsibleUser),
            ),
            'plannedStartDate' => $this->planned_start_date?->toDateString(),
            'plannedTargetDate' => $this->planned_target_date?->toDateString(),
            'weightPercentage' => $this->weight_percentage,
            'displayOrder' => $this->display_order,
        ];
    }

    /** @return array<string, mixed>|null */
    private function safeUser(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }
}
