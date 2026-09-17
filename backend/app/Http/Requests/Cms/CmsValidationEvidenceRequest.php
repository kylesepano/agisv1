<?php

namespace App\Http\Requests\Cms;

use App\Models\MasterList;
use App\Services\RuntimeConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsValidationEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $listId = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->value('id');

        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'validationItemId' => ['nullable', 'integer', 'exists:cms_validation_items,id'],
            'evidenceCategory' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'sourceOrCustodian' => ['nullable', 'string', 'max:255'],
            'confidentialityLevelId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')
                    ->where('master_list_id', $listId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'file' => [
                'required',
                'file',
                'max:'.app(RuntimeConfiguration::class)->documentUploadMaxKilobytes(),
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
            ],
        ];
    }
}
