<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Serializes safe Compliance Monitor assignment history. */
class CmsRecommendationAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'assignmentRoleCode' => $this->assignment_role_code,
            'assignmentReason' => $this->assignment_reason,
            'assignedAt' => $this->assigned_at?->toISOString(),
            'effectiveFrom' => $this->effective_from?->toISOString(),
            'effectiveUntil' => $this->effective_until?->toISOString(),
            'endedAt' => $this->ended_at?->toISOString(),
            'endReason' => $this->end_reason,
            'isCurrent' => $this->is_current,
            'user' => $this->whenLoaded('user', fn () => $this->safeUser($this->user)),
            'assignedBy' => $this->whenLoaded(
                'assigner',
                fn () => $this->safeUser($this->assigner),
            ),
            'endedBy' => $this->whenLoaded('ender', fn () => $this->safeUser($this->ender)),
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
