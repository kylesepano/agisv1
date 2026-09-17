<?php

namespace App\Http\Resources;

use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Office */
/**
 * Serializes an independent office and its Core registry relationships.
 */
class OfficeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $users = $this->whenLoaded('users', fn () => $this->users
            ->sortBy('name')
            ->values()
            ->map(fn ($user): array => [
                'id' => $user->id,
                'employeeId' => $user->employee_id,
                'name' => $user->name,
                'position' => $user->position,
                'role' => $user->role?->name,
                'roleCode' => $user->role?->code,
                'isOfficeHead' => $user->is_office_head,
                'isActive' => $user->is_active,
            ]), collect());

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'acronym' => $this->acronym,
            'officeTypeId' => $this->office_type_id,
            'officeType' => $this->officeType ? [
                'id' => $this->officeType->id,
                'code' => $this->officeType->code,
                'label' => $this->officeType->label,
            ] : null,
            'sector' => $this->sector,
            'contactNumber' => $this->contact_number,
            'headId' => $this->head?->id,
            'headName' => $this->head?->name,
            'head' => $this->head ? [
                'id' => $this->head->id,
                'employeeId' => $this->head->employee_id,
                'name' => $this->head->name,
                'position' => $this->head->position,
            ] : null,
            'usersCount' => $users->count(),
            'users' => $users,
            'auditAreas' => $this->auditAreas->map(fn ($area): array => [
                'id' => $area->id,
                'code' => $area->code,
                'name' => $area->name,
                'description' => $area->description,
            ])->values(),
            'description' => $this->description,
            'isActive' => $this->is_active,
            'isArchived' => $this->trashed(),
            'history' => $this->whenLoaded('auditLogs', fn () => $this->auditLogs
                ->sortByDesc('created_at')
                ->values()
                ->map(fn ($log): array => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'actor' => $log->user?->name ?? 'System',
                    'oldValues' => $log->old_values,
                    'newValues' => $log->new_values,
                    'createdAt' => $log->created_at?->toIso8601String(),
                ]), collect()),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
