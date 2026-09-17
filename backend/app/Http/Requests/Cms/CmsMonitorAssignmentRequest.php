<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates creation or replacement of a Compliance Monitor assignment. */
class CmsMonitorAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'userId' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'reason' => ['nullable', 'string', 'max:2000'],
            'effectiveFrom' => ['nullable', 'date'],
            'effectiveUntil' => [
                'nullable',
                'date',
                $this->filled('effectiveFrom') ? 'after:effectiveFrom' : 'after:now',
            ],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
