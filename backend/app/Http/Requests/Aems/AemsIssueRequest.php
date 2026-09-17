<?php

namespace App\Http\Requests\Aems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates a supported fieldwork issue before workflow actions are applied. */
class AemsIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'exceptionDescription'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'exceptionDescription' => ['required', 'string', 'min:10', 'max:20000'],
            'responsibleOfficeId' => [
                'required',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'riskRatingId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')->whereNull('deleted_at'),
            ],
            'workingPaperVersionIds' => ['sometimes', 'array', 'max:100'],
            'workingPaperVersionIds.*' => ['integer', 'distinct'],
            'evidenceIds' => ['sometimes', 'array', 'max:100'],
            'evidenceIds.*' => ['integer', 'distinct'],
            'lockVersion' => [
                $this->route('issue') ? 'required' : 'nullable',
                'integer',
                'min:1',
            ],
        ];
    }
}
