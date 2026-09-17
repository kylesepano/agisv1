<?php

namespace App\Http\Requests\Aems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\AuditFinding;

/** Validates the criteria-condition-cause-effect finding record. */
class AemsFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'title',
            'criteria',
            'condition',
            'cause',
            'effect',
            'conclusion',
            'significanceClassification',
            'effectClassification',
            'noRecommendationReason',
            'directAuthorityReason',
            'directAuthorityReference',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => $this->filled($field)
                        ? trim((string) $this->input($field))
                        : null,
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $directReason = $this->route('finding')
            ? ['sometimes', 'nullable', 'string', Rule::in(AuditFinding::DIRECT_CREATION_REASONS)]
            : ['required_without:sourceIssueId', 'nullable', 'string', Rule::in(AuditFinding::DIRECT_CREATION_REASONS)];
        $directAuthority = $this->route('finding')
            ? ['sometimes', 'nullable', 'string', 'min:3', 'max:4000']
            : ['required_without:sourceIssueId', 'nullable', 'string', 'min:3', 'max:4000'];

        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'criteria' => ['required', 'string', 'min:3', 'max:20000'],
            'condition' => ['required', 'string', 'min:3', 'max:20000'],
            'cause' => ['required', 'string', 'min:3', 'max:20000'],
            'effect' => ['required', 'string', 'min:3', 'max:20000'],
            'conclusion' => ['required', 'string', 'min:3', 'max:20000'],
            'sourceIssueId' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('audit_issues', 'id')->whereNull('deleted_at'),
            ],
            'directAuthorityReason' => $directReason,
            'directAuthorityReference' => $directAuthority,
            'significanceClassification' => ['nullable', 'string', 'max:50'],
            'effectClassification' => ['nullable', 'string', 'max:50'],
            'noRecommendationReason' => ['nullable', 'string', 'min:5', 'max:4000'],
            'riskRatingId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')->whereNull('deleted_at'),
            ],
            'responsibleOfficeId' => [
                'required',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'workingPaperVersionIds' => ['sometimes', 'array', 'max:100'],
            'workingPaperVersionIds.*' => ['integer', 'distinct'],
            'evidenceIds' => ['sometimes', 'array', 'max:100'],
            'evidenceIds.*' => ['integer', 'distinct'],
            'fieldworkRecordIds' => ['sometimes', 'array', 'max:100'],
            'fieldworkRecordIds.*' => ['integer', 'distinct'],
            'fieldworkRecordVersionIds' => ['sometimes', 'array', 'max:100'],
            'fieldworkRecordVersionIds.*' => ['integer', 'distinct'],
            'procedureIds' => ['sometimes', 'array', 'max:100'],
            'procedureIds.*' => ['integer', 'distinct'],
            'lockVersion' => [
                $this->route('finding') ? 'required' : 'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
