<?php

namespace App\Http\Requests\Aems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\AemsFieldworkRecord;

/** Validates one editable Fieldwork Record version and its traceability links. */
class AemsFieldworkRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'location', 'objective', 'procedurePerformed', 'populationDescription',
            'sampleDescription', 'analysis', 'result', 'conclusion', 'changeReason',
        ] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->filled($field) ? trim((string) $this->input($field)) : null]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'recordType' => ['required', Rule::in(AemsFieldworkRecord::TYPES)],
            'procedureId' => ['required', 'integer', Rule::exists('audit_program_procedures', 'id')->whereNull('deleted_at')],
            'auditAreaId' => ['required', 'integer', Rule::exists('audit_areas', 'id')->whereNull('deleted_at')],
            'auditFocusId' => ['required', 'integer', Rule::exists('audit_focuses', 'id')->whereNull('deleted_at')],
            'performedOn' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:500'],
            'objective' => ['nullable', 'string', 'max:10000'],
            'procedurePerformed' => ['required', 'string', 'min:5', 'max:20000'],
            'populationDescription' => ['nullable', 'string', 'max:10000'],
            'sampleDescription' => ['nullable', 'string', 'max:10000'],
            'analysis' => ['nullable', 'string', 'max:20000'],
            'result' => ['required', 'string', 'min:3', 'max:20000'],
            'conclusion' => ['required', 'string', 'min:3', 'max:20000'],
            'executionStatus' => ['required', Rule::in(['PLANNED', 'IN_PROGRESS', 'COMPLETED'])],
            'participants' => ['required', 'array', 'min:1', 'max:100'],
            'participants.*.userId' => ['nullable', 'integer', 'exists:users,id'],
            'participants.*.participantName' => ['nullable', 'string', 'max:255'],
            'participants.*.participantRole' => ['nullable', 'string', 'max:120'],
            'participants.*.officeId' => ['nullable', 'integer', 'exists:offices,id'],
            'workingPaperIds' => ['sometimes', 'array', 'max:100'],
            'workingPaperIds.*' => ['integer', 'distinct'],
            'workingPaperLinks' => ['sometimes', 'array', 'max:100'],
            'workingPaperLinks.*.workingPaperId' => ['required', 'integer'],
            'workingPaperLinks.*.workingPaperVersionId' => ['nullable', 'integer'],
            'evidenceIds' => ['required', 'array', 'min:1', 'max:100'],
            'evidenceIds.*' => ['integer', 'distinct'],
            'relatedTasks' => ['sometimes', 'array', 'max:100'],
            'relatedTasks.*' => ['string', 'max:255'],
            'relatedRecords' => ['sometimes', 'array', 'max:100'],
            'relatedRecords.*' => ['string', 'max:255'],
            'changeReason' => [$this->route('record') ? 'required' : 'nullable', 'string', 'min:5', 'max:4000'],
            'lockVersion' => [$this->route('record') ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
