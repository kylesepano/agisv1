<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CmsValidationAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->assignment_role_code,
            'user' => $this->whenLoaded('user', fn () => $this->safeUser($this->user)),
            'assignedBy' => $this->whenLoaded('assigner', fn () => $this->safeUser($this->assigner)),
            'assignedAt' => $this->assigned_at?->toISOString(),
            'assignmentReason' => $this->assignment_reason,
            'effectiveFrom' => $this->effective_from?->toISOString(),
            'effectiveUntil' => $this->effective_until?->toISOString(),
            'endedBy' => $this->whenLoaded('ender', fn () => $this->safeUser($this->ender)),
            'endedAt' => $this->ended_at?->toISOString(),
            'endReason' => $this->end_reason,
            'isCurrent' => $this->is_current,
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
