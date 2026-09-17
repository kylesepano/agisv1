<?php

namespace App\Http\Requests\Aems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AemsEvidenceAssessmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        $rating = ['nullable', 'string', Rule::in([
            'YES', 'NO', 'PARTIAL', 'HIGH', 'MEDIUM', 'LOW', 'NOT_ASSESSED',
            'ADEQUATE', 'INADEQUATE', 'NOT_APPLICABLE',
        ])];
        return [
            'evidenceId' => ['required', 'integer', Rule::exists('audit_evidence', 'id')->whereNull('deleted_at')],
            'evidenceRequestId' => ['nullable', 'integer', Rule::exists('aems_evidence_requests', 'id')->whereNull('deleted_at')],
            'documentVersionId' => ['required', 'integer', Rule::exists('document_versions', 'id')],
            'sufficiency' => $rating, 'appropriateness' => $rating, 'relevance' => $rating,
            'reliability' => $rating, 'competence' => $rating, 'accuracy' => $rating,
            'completeness' => $rating, 'corroboration' => $rating, 'contradiction' => $rating,
            'authenticity' => $rating, 'integrity' => $rating,
            'confidentiality' => ['nullable', 'string', Rule::in(['PUBLIC', 'INTERNAL', 'CONFIDENTIAL', 'RESTRICTED'])],
            'isRestricted' => ['sometimes', 'boolean'],
            'accessRestrictions' => ['nullable', 'string', 'max:10000'],
            'limitations' => ['nullable', 'string', 'max:10000'],
            'evidenceGaps' => ['nullable', 'string', 'max:10000'],
            'exceptionRequired' => ['sometimes', 'boolean'],
            'exceptionReason' => ['nullable', 'string', 'max:10000'],
            'changeReason' => ['nullable', 'string', 'min:5', 'max:4000'],
            'evidenceOutcome' => ['nullable', 'string', Rule::in(['ACCEPTED', 'LIMITED', 'ADDITIONAL_REQUIRED', 'REJECTED', 'DUPLICATE'])],
        ];
    }
}
