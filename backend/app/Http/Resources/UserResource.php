<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
/**
 * Serializes user identity, employment, office, role, scope, and account state.
 */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['office', 'role.permissions', 'roles.permissions']);
        $roles = $this->effectiveRoles();
        $permissions = $roles
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('code')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'id' => $this->id,
            'employeeId' => $this->employee_id,
            'email' => $this->email,
            'name' => $this->name,
            'firstName' => $this->first_name,
            'middleName' => $this->middle_name,
            'lastName' => $this->last_name,
            'extension' => $this->name_extension,
            'initials' => $this->initials,
            'role' => $this->role?->name,
            'roleCode' => $this->role?->code,
            'roleId' => $this->role_id,
            'roles' => $roles->map(fn ($role): array => [
                'id' => $role->id,
                'code' => $role->code,
                'name' => $role->name,
                'isPrimary' => (int) $role->id === (int) $this->role_id,
                'officeAccessScope' => $role->office_access_scope,
                'engagementAccessScope' => $role->engagement_access_scope,
            ])->values(),
            'accessScopes' => [
                'office' => $this->hasGlobalOfficeAccess() ? 'ALL' : 'OWN_OFFICE',
                'engagement' => $this->hasGlobalEngagementAccess() ? 'ALL' : 'ASSIGNED',
            ],
            'office' => $this->office?->name,
            'officeCode' => $this->office?->code,
            'position' => $this->position,
            'employmentType' => $this->employment_type,
            'contactNumber' => $this->contact_number,
            'birthDate' => $this->birth_date?->toDateString(),
            'isOfficeHead' => $this->is_office_head,
            'isLocked' => $this->isLocked(),
            'permissions' => $permissions,
        ];
    }
}
