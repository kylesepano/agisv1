<?php

namespace App\Http\Requests\Core;

use App\Models\WorkflowDefinition;
use App\Models\WorkflowStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates reusable workflow steps, transitions, roles, and SLA settings.
 */
class WorkflowDefinitionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalize = fn (mixed $value): string => str((string) $value)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->toString();

        $steps = collect($this->input('steps', []))
            ->map(fn (array $step): array => [
                ...$step,
                'code' => $normalize($step['code'] ?? ''),
                'stepType' => strtoupper((string) ($step['stepType'] ?? 'INTERMEDIATE')),
            ])
            ->values()
            ->all();
        $transitions = collect($this->input('transitions', []))
            ->map(fn (array $transition): array => [
                ...$transition,
                'code' => $normalize($transition['code'] ?? ''),
                'fromStepCode' => $normalize($transition['fromStepCode'] ?? ''),
                'toStepCode' => $normalize($transition['toStepCode'] ?? ''),
            ])
            ->values()
            ->all();

        $this->merge([
            'code' => $normalize($this->input('code')),
            'moduleCode' => strtoupper((string) $this->input('moduleCode')),
            'subjectType' => $normalize($this->input('subjectType')),
            'steps' => $steps,
            'transitions' => $transitions,
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'name' => ['required', 'string', 'max:255'],
            'moduleCode' => ['required', Rule::in(WorkflowDefinition::MODULES)],
            'subjectType' => ['required', 'string', 'max:100', 'regex:/^[A-Z][A-Z0-9_]*$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'steps' => ['required', 'array', 'min:2', 'max:30'],
            'steps.*.code' => ['required', 'string', 'max:80', 'distinct'],
            'steps.*.name' => ['required', 'string', 'max:255'],
            'steps.*.stepType' => ['required', Rule::in(WorkflowStep::TYPES)],
            'steps.*.responsibleRoleId' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->whereNull('deleted_at'),
            ],
            'steps.*.slaHours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'steps.*.instructions' => ['nullable', 'string', 'max:5000'],
            'transitions' => ['required', 'array', 'min:1', 'max:100'],
            'transitions.*.code' => ['required', 'string', 'max:80', 'distinct'],
            'transitions.*.name' => ['required', 'string', 'max:255'],
            'transitions.*.fromStepCode' => ['required', 'string', 'max:80'],
            'transitions.*.toStepCode' => ['required', 'string', 'max:80'],
            'transitions.*.actorRoleId' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->whereNull('deleted_at'),
            ],
            'transitions.*.requiredPermissionId' => [
                'required',
                'integer',
                Rule::exists('permissions', 'id'),
            ],
            'transitions.*.requiresComment' => ['required', 'boolean'],
            'transitions.*.enforceSeparationOfDuties' => ['required', 'boolean'],
            'transitions.*.isActive' => ['sometimes', 'boolean'],
        ];
    }
}
