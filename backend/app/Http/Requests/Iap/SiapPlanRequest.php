<?php

namespace App\Http\Requests\Iap;

use App\Models\StrategicInternalAuditPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates strategic-plan periods, themes, objectives, priorities, and outcomes.
 */
class SiapPlanRequest extends FormRequest
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
        /** @var StrategicInternalAuditPlan|null $strategicPlan */
        $strategicPlan = $this->route('strategicPlan');

        return [
            'planCode' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('strategic_internal_audit_plans', 'plan_code')
                    ->ignore($strategicPlan?->getKey()),
            ],
            'startYear' => ['required', 'integer', 'min:2000', 'max:2199'],
            'endYear' => ['required', 'integer', 'gt:startYear', 'max:2200'],
            'title' => ['required', 'string', 'max:255'],
            'strategicContext' => ['nullable', 'string', 'max:15000'],
            'vision' => ['nullable', 'string', 'max:10000'],
            'missionAlignment' => ['nullable', 'string', 'max:10000'],
            'planningMethodology' => ['nullable', 'string', 'max:15000'],
            'expectedOutcomes' => ['required', 'string', 'max:15000'],
            'coordinatorId' => ['nullable', 'integer', 'exists:users,id'],
            'objectives' => ['required', 'array', 'min:1', 'max:30'],
            'objectives.*.objectiveCode' => [
                'required',
                'string',
                'max:40',
                'distinct:ignore_case',
            ],
            'objectives.*.title' => ['required', 'string', 'max:255'],
            'objectives.*.description' => ['required', 'string', 'max:10000'],
            'objectives.*.expectedOutcome' => ['required', 'string', 'max:10000'],
            'objectives.*.auditAreaIds' => ['required', 'array', 'min:1'],
            'objectives.*.auditAreaIds.*' => [
                'required',
                'integer',
                'distinct',
                'exists:audit_areas,id',
            ],
            'priorities' => ['required', 'array', 'min:1', 'max:30'],
            'priorities.*.priorityCode' => [
                'required',
                'string',
                'max:40',
                'distinct:ignore_case',
            ],
            'priorities.*.title' => ['required', 'string', 'max:255'],
            'priorities.*.theme' => ['required', 'string', 'max:255'],
            'priorities.*.description' => ['required', 'string', 'max:10000'],
            'priorities.*.expectedOutcome' => ['required', 'string', 'max:10000'],
            'lockVersion' => [
                $strategicPlan ? 'required' : 'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = (int) $this->input('startYear');
            $end = (int) $this->input('endYear');
            if ($start > 0 && $end > 0 && $end - $start > 10) {
                $validator->errors()->add(
                    'endYear',
                    'A Strategic Internal Audit Plan may cover at most 10 years.',
                );
            }
        });
    }
}
