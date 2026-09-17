<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Structural validation for a management-reported Progress Update draft. */
class CmsProgressUpdateDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'reportingPeriodStart' => [$creating ? 'required' : 'sometimes', 'date'],
            'reportingPeriodEnd' => [
                $creating ? 'required' : 'sometimes',
                'date',
                'after_or_equal:reportingPeriodStart',
            ],
            'accomplishmentSummary' => ['nullable', 'string', 'max:10000'],
            'managementReportedOverallPercentage' => ['nullable', 'numeric', 'between:0,100'],
            'issuesAndConstraints' => ['nullable', 'string', 'max:10000'],
            'correctiveActionsForDelays' => ['nullable', 'string', 'max:10000'],
            'nextSteps' => ['nullable', 'string', 'max:10000'],
            'forecastCompletionDate' => ['nullable', 'date'],
            'managementDeclaration' => ['nullable', 'string', 'max:10000'],
            'generalEvidenceExplanation' => ['nullable', 'string', 'max:10000'],
            'milestoneProgress' => ['sometimes', 'array', 'max:100'],
            'milestoneProgress.*.id' => ['nullable', 'integer'],
            'milestoneProgress.*.actionPlanMilestoneId' => [
                'required',
                'integer',
                'exists:cms_action_plan_milestones,id',
            ],
            'milestoneProgress.*.managementReportedStatusCode' => [
                'required',
                Rule::in([
                    'NOT_STARTED',
                    'IN_PROGRESS',
                    'REPORTED_COMPLETED',
                    'DELAYED',
                    'ON_HOLD',
                ]),
            ],
            'milestoneProgress.*.managementReportedPercentage' => [
                'required',
                'numeric',
                'between:0,100',
            ],
            'milestoneProgress.*.accomplishmentDescription' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'milestoneProgress.*.issuesAndConstraints' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'milestoneProgress.*.nextStep' => ['nullable', 'string', 'max:10000'],
            'milestoneProgress.*.forecastCompletionDate' => ['nullable', 'date'],
            'milestoneProgress.*.noEvidenceExplanation' => [
                'nullable',
                'string',
                'max:10000',
            ],
            'milestoneProgress.*.displayOrder' => ['required', 'integer', 'min:1'],
        ];
    }
}
