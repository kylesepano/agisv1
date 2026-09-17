<?php

namespace App\Http\Requests\Cms;

use App\Services\RuntimeConfiguration;
use Illuminate\Foundation\Http\FormRequest;

/** Validates an auditee's file response to an AEMS Evidence Request. */
class CmsAemsEvidenceRequestResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'sourceDescription', 'responseNote'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->filled($field) ? trim((string) $this->input($field)) : null]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'sourceDescription' => ['required', 'string', 'min:3', 'max:5000'],
            'dateObtained' => ['required', 'date', 'before_or_equal:today'],
            'responseNote' => ['nullable', 'string', 'max:5000'],
            'file' => [
                'required',
                'file',
                'max:'.app(RuntimeConfiguration::class)->documentUploadMaxKilobytes(),
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png',
            ],
        ];
    }
}
