<?php

namespace App\Http\Requests\Aems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AemsEvidenceRequestEvidenceRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'evidenceId' => ['required', 'integer', Rule::exists('audit_evidence', 'id')->whereNull('deleted_at')],
            'documentVersionId' => ['required', 'integer', Rule::exists('document_versions', 'id')],
            'receiptNotes' => ['nullable', 'string', 'max:4000'],
            'receiptOutcome' => ['nullable', 'string', Rule::in(['COMPLETE', 'PARTIAL', 'RESTRICTED', 'REJECTED'])],
            'receivedForm' => ['nullable', 'string', 'max:40'],
            'acquisitionMethod' => ['nullable', 'string', 'max:50'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
