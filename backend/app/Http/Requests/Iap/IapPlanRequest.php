<?php

namespace App\Http\Requests\Iap;

use App\Models\InternalAuditPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates Annual Plan identity, fiscal period, capacity, and management metadata.
 */
class IapPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'planCode' => $this->filled('planCode')
                ? strtoupper(trim((string) $this->input('planCode')))
                : null,
            'title' => trim((string) $this->input('title')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var InternalAuditPlan|null $plan */
        $plan = $this->route('plan');

        return [
            'planCode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('internal_audit_plans', 'plan_code')
                    ->ignore($plan?->getKey()),
            ],
            'fiscalYear' => ['required', 'integer', 'min:2000', 'max:2200'],
            'planningPeriodTypeId' => ['required', 'integer', 'exists:master_list_items,id'],
            'planningPeriodStart' => ['required', 'date'],
            'planningPeriodEnd' => ['required', 'date', 'after_or_equal:planningPeriodStart'],
            'title' => ['required', 'string', 'max:255'],
            'executiveSummary' => ['nullable', 'string', 'max:10000'],
            'planningMethodology' => ['nullable', 'string', 'max:10000'],
            'overallObjective' => ['required', 'string', 'max:10000'],
            'overallScope' => ['required', 'string', 'max:10000'],
            'limitations' => ['nullable', 'string', 'max:10000'],
            'preparedBy' => ['sometimes', 'integer', 'exists:users,id'],
            'coordinatorId' => ['nullable', 'integer', 'exists:users,id'],
            'lockVersion' => [$plan ? 'required' : 'sometimes', 'integer', 'min:1'],
        ];
    }
}
