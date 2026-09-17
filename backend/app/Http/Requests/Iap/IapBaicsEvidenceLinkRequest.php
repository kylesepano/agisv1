<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;

class IapBaicsEvidenceLinkRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'methodId' => ['nullable', 'integer', 'exists:iap_baics_methods,id'],
            'documentVersionId' => ['required', 'integer', 'exists:document_versions,id'],
            'evidenceRole' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
