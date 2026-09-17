<?php

namespace App\Http\Requests\Iap;

use App\Models\IapRiskPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a risk period and its weighted scoring criteria.
 */
class IapRiskPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'periodCode' => $this->filled('periodCode')
                ? strtoupper(trim((string) $this->input('periodCode')))
                : null,
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var IapRiskPeriod|null $period */
        $period = $this->route('period');

        return [
            'periodCode' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('iap_risk_periods', 'period_code')->ignore($period?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'assessmentYear' => ['required', 'integer', 'min:2000', 'max:2200'],
            'startDate' => ['required', 'date'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.criterionId' => [
                'required',
                'integer',
                'distinct',
                'exists:master_list_items,id',
            ],
            'criteria.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'lockVersion' => [$period ? 'required' : 'sometimes', 'integer', 'min:1'],
        ];
    }
}
