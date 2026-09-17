<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsExtensionEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'evidenceCategory' => ['required', 'string', 'max:80'],
            'sourceOrCustodian' => ['nullable', 'string', 'max:255'],
            'confidentialityLevelId' => ['required', 'integer', 'exists:master_list_items,id'],
            'file' => ['required', 'file', 'max:102400'],
        ];
    }
}
