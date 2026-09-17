<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/** Structural validation for creating or replacing the content of a draft. */
class CmsActionPlanDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'planSummary' => ['nullable', 'string', 'max:10000'],
            'implementationStrategy' => ['nullable', 'string', 'max:10000'],
            'expectedOutcome' => ['nullable', 'string', 'max:10000'],
            'rootCauseResponse' => ['nullable', 'string', 'max:10000'],
            'resourcesRequired' => ['nullable', 'string', 'max:10000'],
            'dependencies' => ['nullable', 'string', 'max:10000'],
            'risksAndConstraints' => ['nullable', 'string', 'max:10000'],
            'plannedStartDate' => ['nullable', 'date'],
            'plannedTargetDate' => ['nullable', 'date'],
            'ownerOfficeId' => ['nullable', 'integer', 'exists:offices,id'],
            'focalUserId' => ['nullable', 'integer', 'exists:users,id'],
            'milestones' => ['sometimes', 'array', 'max:100'],
            'milestones.*.id' => ['nullable', 'integer'],
            'milestones.*.sequenceNumber' => ['required', 'integer', 'min:1'],
            'milestones.*.title' => ['required', 'string', 'max:255'],
            'milestones.*.description' => ['nullable', 'string', 'max:10000'],
            'milestones.*.expectedOutput' => ['required', 'string', 'max:10000'],
            'milestones.*.successIndicator' => ['nullable', 'string', 'max:10000'],
            'milestones.*.verificationMethod' => ['nullable', 'string', 'max:10000'],
            'milestones.*.responsibleOfficeId' => ['required', 'integer', 'exists:offices,id'],
            'milestones.*.responsibleUserId' => ['nullable', 'integer', 'exists:users,id'],
            'milestones.*.plannedStartDate' => ['nullable', 'date'],
            'milestones.*.plannedTargetDate' => ['required', 'date'],
            'milestones.*.weightPercentage' => ['nullable', 'numeric', 'gt:0', 'lte:100'],
            'milestones.*.displayOrder' => ['required', 'integer', 'min:1'],
        ];
    }
}
