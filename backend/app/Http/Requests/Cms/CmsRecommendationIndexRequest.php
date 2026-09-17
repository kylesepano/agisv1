<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the server-side CMS registry query contract. */
class CmsRecommendationIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['assigned', 'hasTargetDate', 'overdue'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var(
                        $this->input($field),
                        FILTER_VALIDATE_BOOLEAN,
                        FILTER_NULL_ON_FAILURE,
                    ),
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => [
                'nullable',
                Rule::in([
                    'TRANSFERRED',
                    'FOR_ACTION_PLAN',
                    'MONITORING',
                    'FOR_VALIDATION',
                    'PARTIALLY_IMPLEMENTED',
                    'IMPLEMENTED',
                ]),
            ],
            'officeId' => ['nullable', 'integer', 'exists:offices,id'],
            'risk' => ['nullable', 'string', 'max:80'],
            'confidentiality' => ['nullable', 'string', 'max:80'],
            'monitorId' => ['nullable', 'integer', 'exists:users,id'],
            'assigned' => ['nullable', 'boolean'],
            'hasTargetDate' => ['nullable', 'boolean'],
            'overdue' => ['nullable', 'boolean'],
            'transferredFrom' => ['nullable', 'date'],
            'transferredTo' => ['nullable', 'date', 'after_or_equal:transferredFrom'],
            'targetFrom' => ['nullable', 'date'],
            'targetTo' => ['nullable', 'date', 'after_or_equal:targetFrom'],
            'sortBy' => [
                'nullable',
                Rule::in([
                    'recommendationCode',
                    'transferredAt',
                    'targetDate',
                    'responsibleOffice',
                    'risk',
                    'status',
                    'assignedMonitor',
                ]),
            ],
            'sortDirection' => ['nullable', Rule::in(['asc', 'desc'])],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
