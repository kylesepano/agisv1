<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates engagement team members, leadership, allocations, and skill assignments.
 */
class IapTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'members' => ['required', 'array', 'min:2'],
            'members.*.userId' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'members.*.teamRoleId' => ['required', 'integer', 'exists:master_list_items,id'],
            'members.*.plannedPersonDays' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'members.*.notes' => ['nullable', 'string', 'max:5000'],
            'lockVersion' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
