<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates assessment scores, control effectiveness, justification, and evidence.
 */
class IapRiskAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'officeId' => ['required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'auditAreaId' => ['required', 'integer', Rule::exists('audit_areas', 'id')->whereNull('deleted_at')],
            'assessmentDate' => ['required', 'date'],
            'lastAuditDate' => ['nullable', 'date', 'before_or_equal:assessmentDate'],
            'inherentRiskNotes' => ['nullable', 'string', 'max:10000'],
            'controlEnvironmentNotes' => ['nullable', 'string', 'max:10000'],
            'overrideRiskLevelId' => ['nullable', 'integer', 'exists:master_list_items,id'],
            'overrideReason' => ['nullable', 'required_with:overrideRiskLevelId', 'string', 'max:5000'],
            'justification' => ['required', 'string', 'max:10000'],
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.criterionId' => ['required', 'integer', 'distinct', 'exists:master_list_items,id'],
            'scores.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'scores.*.rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'scores.*.comment' => ['nullable', 'string', 'max:5000'],
            'lockVersion' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
