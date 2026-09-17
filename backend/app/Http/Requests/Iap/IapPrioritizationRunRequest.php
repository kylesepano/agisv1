<?php

namespace App\Http\Requests\Iap;

use App\Models\IapPrioritizationRun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates prioritization-run metadata and its validated assessment source.
 */
class IapPrioritizationRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'runCode' => $this->filled('runCode')
                ? strtoupper(trim((string) $this->input('runCode')))
                : null,
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var IapPrioritizationRun|null $run */
        $run = $this->route('prioritization');

        return [
            'runCode' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('iap_prioritization_runs', 'run_code')->ignore($run?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'riskPeriodId' => [$run ? 'sometimes' : 'required', 'integer', 'exists:iap_risk_periods,id'],
            'methodology' => ['required', 'string', 'max:10000'],
            'lockVersion' => [$run ? 'required' : 'sometimes', 'integer', 'min:1'],
        ];
    }
}
