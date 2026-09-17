<?php

namespace App\Http\Requests\Aems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the SCR-212 structured engagement-scope workspace payload. */
class AemsScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'officeId' => ['required', 'integer', 'gt:0', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'scopeBoundaries' => ['nullable', 'string', 'max:10000'],
            'scopeLimitations' => ['nullable', 'string', 'max:10000'],
            'scopeSourceVariance' => ['nullable', 'array'],
            'scopeSourceVariance.decision' => ['nullable', 'string', 'in:ALIGNED,VARIANCE_APPROVED,NOT_APPLICABLE'],
            'scopeSourceVariance.explanation' => ['nullable', 'string', 'max:10000'],
            'scopeSourceVariance.authority' => ['nullable', 'string', 'max:255'],
            'areaCoverage' => ['required', 'array', 'min:1'],
            'areaCoverage.*.auditAreaId' => [
                'required',
                'integer',
                'gt:0',
                'distinct',
                Rule::exists('audit_areas', 'id')->whereNull('deleted_at'),
            ],
            'areaCoverage.*.boundary' => ['nullable', 'string', 'max:10000'],
            'areaCoverage.*.limitations' => ['nullable', 'string', 'max:10000'],
            'areaCoverage.*.sourceVariance' => ['nullable', 'string', 'max:10000'],
            'areaCoverage.*.objective' => ['nullable', 'string', 'max:10000'],
            'areaCoverage.*.focusIds' => ['sometimes', 'array'],
            'areaCoverage.*.focusIds.*' => [
                'integer',
                'gt:0',
                'distinct',
                Rule::exists('audit_focuses', 'id')->whereNull('deleted_at'),
            ],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
