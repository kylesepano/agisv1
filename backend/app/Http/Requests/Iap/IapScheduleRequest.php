<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates schedule dates, proposed team assignments, and revision context.
 */
class IapScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'plannedStartDate' => ['required', 'date'],
            'plannedEndDate' => ['required', 'date', 'after_or_equal:plannedStartDate'],
            'expectedReportDate' => ['required', 'date', 'after_or_equal:plannedEndDate'],
            'members' => ['required', 'array', 'min:2'],
            'members.*.userId' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at'),
            ],
            'members.*.teamRoleId' => [
                'required',
                'integer',
                'exists:master_list_items,id',
            ],
            'members.*.plannedPersonDays' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999.99',
            ],
            'members.*.notes' => ['nullable', 'string', 'max:5000'],
            'reason' => ['nullable', 'string', 'min:10', 'max:5000'],
            'acknowledgeConflicts' => ['sometimes', 'boolean'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
