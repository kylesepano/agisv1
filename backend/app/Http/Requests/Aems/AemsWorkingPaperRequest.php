<?php

namespace App\Http\Requests\Aems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates one immutable Working Paper content version.
 */
class AemsWorkingPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach ([
            'title',
            'objective',
            'procedurePerformed',
            'populationDescription',
            'sampleDescription',
            'result',
            'conclusion',
            'noEvidenceReason',
            'changeReason',
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
        return [
            'procedureId' => [
                'required',
                'integer',
                Rule::exists('audit_program_procedures', 'id')->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'objective' => ['required', 'string', 'min:3', 'max:10000'],
            'procedurePerformed' => ['required', 'string', 'min:5', 'max:20000'],
            'populationDescription' => ['nullable', 'string', 'max:10000'],
            'sampleDescription' => ['nullable', 'string', 'max:10000'],
            'result' => ['required', 'string', 'min:3', 'max:20000'],
            'conclusion' => ['required', 'string', 'min:3', 'max:20000'],
            'noEvidenceReason' => ['nullable', 'string', 'min:5', 'max:4000'],
            'crossReferences' => ['sometimes', 'array', 'max:100'],
            'crossReferences.*' => ['required', 'string', 'max:255'],
            'evidenceIds' => ['sometimes', 'array', 'max:100'],
            'evidenceIds.*' => ['integer', 'distinct'],
            'changeReason' => [
                $this->route('paper') ? 'required' : 'nullable',
                'string',
                'min:5',
                'max:4000',
            ],
            'lockVersion' => [
                $this->route('paper') ? 'required' : 'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
