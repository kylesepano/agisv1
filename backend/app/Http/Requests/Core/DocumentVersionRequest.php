<?php

namespace App\Http\Requests\Core;

use App\Services\RuntimeConfiguration;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an immutable replacement version and its required change context.
 */
class DocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['versionLabel', 'changeSummary'] as $field) {
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
            'versionLabel' => ['required', 'string', 'max:60'],
            'changeSummary' => ['required', 'string', 'max:2000'],
            'file' => [
                'required',
                'file',
                'max:'.app(RuntimeConfiguration::class)->documentUploadMaxKilobytes(),
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
            ],
        ];
    }
}
