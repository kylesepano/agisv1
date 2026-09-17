<?php

namespace App\Http\Requests\Cms;

use App\Models\CmsValidationItem;
use App\Models\CmsValidationVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsValidationDraftRequest extends FormRequest
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
            'validationScope' => ['nullable', 'string', 'max:20000'],
            'validationObjectives' => ['nullable', 'string', 'max:20000'],
            'methodologySummary' => ['nullable', 'string', 'max:20000'],
            'overallWorkPerformed' => ['nullable', 'string', 'max:20000'],
            'overallEvidenceSummary' => ['nullable', 'string', 'max:20000'],
            'limitations' => ['nullable', 'string', 'max:20000'],
            'professionalJudgmentRationale' => ['nullable', 'string', 'max:20000'],
            'proposedConclusionCode' => ['nullable', Rule::in(CmsValidationVersion::CONCLUSIONS)],
            'validatedCompletionPercentage' => ['nullable', 'numeric', 'between:0,100'],
            'validationItems' => ['sometimes', 'array', 'max:200'],
            'validationItems.*.id' => ['nullable', 'integer', 'exists:cms_validation_items,id'],
            'validationItems.*.scopeCode' => ['required', Rule::in(CmsValidationItem::SCOPES)],
            'validationItems.*.actionPlanMilestoneId' => [
                'nullable',
                'integer',
                'exists:cms_action_plan_milestones,id',
            ],
            'validationItems.*.milestoneProgressId' => [
                'nullable',
                'integer',
                'exists:cms_milestone_progress,id',
            ],
            'validationItems.*.sequenceNumber' => ['required', 'integer', 'min:1'],
            'validationItems.*.criterion' => ['nullable', 'string', 'max:20000'],
            'validationItems.*.procedurePerformed' => ['nullable', 'string', 'max:20000'],
            'validationItems.*.populationOrSource' => ['nullable', 'string', 'max:20000'],
            'validationItems.*.sampleDescription' => ['nullable', 'string', 'max:20000'],
            'validationItems.*.resultSummary' => ['nullable', 'string', 'max:20000'],
            'validationItems.*.exceptionSummary' => ['nullable', 'string', 'max:20000'],
            'validationItems.*.itemConclusionCode' => [
                'nullable',
                Rule::in(CmsValidationItem::CONCLUSIONS),
            ],
            'validationItems.*.validatedMilestonePercentage' => [
                'nullable',
                'numeric',
                'between:0,100',
            ],
            'validationItems.*.followUpRequired' => ['nullable', 'boolean'],
            'validationItems.*.displayOrder' => ['required', 'integer', 'min:1'],
            'evidenceAssessments' => ['sometimes', 'array', 'max:500'],
            'evidenceAssessments.*.id' => [
                'nullable',
                'integer',
                'exists:cms_validation_evidence_assessments,id',
            ],
            'evidenceAssessments.*.validationItemId' => [
                'nullable',
                'integer',
                'exists:cms_validation_items,id',
            ],
            'evidenceAssessments.*.progressEvidenceLinkId' => [
                'nullable',
                'integer',
                'exists:cms_progress_evidence_links,id',
            ],
            'evidenceAssessments.*.validationEvidenceLinkId' => [
                'nullable',
                'integer',
                'exists:cms_validation_evidence_links,id',
            ],
            'evidenceAssessments.*.evidenceSourceCode' => [
                'required',
                Rule::in(['MANAGEMENT_SUBMITTED', 'VALIDATOR_OBTAINED']),
            ],
            'evidenceAssessments.*.relevanceCode' => [
                'required',
                Rule::in(['RELEVANT', 'PARTIALLY_RELEVANT', 'NOT_RELEVANT', 'NOT_ASSESSED']),
            ],
            'evidenceAssessments.*.reliabilityCode' => [
                'required',
                Rule::in(['RELIABLE', 'LIMITED_RELIABILITY', 'UNRELIABLE', 'NOT_ASSESSED']),
            ],
            'evidenceAssessments.*.sufficiencyCode' => [
                'required',
                Rule::in(['SUFFICIENT', 'PARTIALLY_SUFFICIENT', 'INSUFFICIENT', 'NOT_ASSESSED']),
            ],
            'evidenceAssessments.*.reliedUpon' => ['required', 'boolean'],
            'evidenceAssessments.*.assessmentSummary' => ['nullable', 'string', 'max:20000'],
            'evidenceAssessments.*.limitationSummary' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
