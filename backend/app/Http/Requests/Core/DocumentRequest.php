<?php

namespace App\Http\Requests\Core;

use App\Models\Document;
use App\Models\MasterList;
use App\Services\RuntimeConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates document metadata, confidentiality, links, and initial upload input.
 */
class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('confidentialityLevelId')) {
            $internalId = MasterList::query()
                ->where('code', 'DOCUMENT_CONFIDENTIALITY')
                ->first()
                ?->items()
                ->where('code', 'INTERNAL')
                ->where('is_active', true)
                ->value('id');
            if ($internalId) {
                $this->merge(['confidentialityLevelId' => $internalId]);
            }
        }

        if ($this->has('links') && is_string($this->input('links'))) {
            $decoded = json_decode((string) $this->input('links'), true);
            $this->merge(['links' => is_array($decoded) ? $decoded : null]);
        }

        foreach (['title', 'referenceNumber', 'issuingAuthority', 'version', 'description'] as $field) {
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
        /** @var Document|null $document */
        $document = $this->route('document');
        $documentTypeListId = MasterList::query()
            ->where('code', 'DOCUMENT_TYPE')
            ->value('id');
        $confidentialityListId = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->value('id');

        return [
            'documentTypeId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')
                    ->where('master_list_id', $documentTypeListId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'confidentialityLevelId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')
                    ->where('master_list_id', $confidentialityListId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'title' => ['required', 'string', 'max:255'],
            'referenceNumber' => ['nullable', 'string', 'max:120'],
            'issuingAuthority' => ['nullable', 'string', 'max:255'],
            'publicationDate' => ['nullable', 'date'],
            'version' => [$document ? 'prohibited' : 'nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:3000'],
            'isActive' => ['sometimes', 'boolean'],
            'file' => [
                $document ? 'prohibited' : 'required',
                'file',
                'max:'.app(RuntimeConfiguration::class)->documentUploadMaxKilobytes(),
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv',
            ],
            'links' => ['sometimes', 'array', 'max:100'],
            'links.*.module' => ['required', 'string', 'max:20'],
            'links.*.recordType' => ['required', 'string', 'max:40'],
            'links.*.recordId' => ['required', 'integer', 'min:0'],
        ];
    }
}
