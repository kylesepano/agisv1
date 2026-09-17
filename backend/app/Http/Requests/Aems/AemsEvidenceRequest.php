<?php

namespace App\Http\Requests\Aems;

use App\Models\MasterList;
use App\Services\RuntimeConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates immutable AEMS evidence uploads and replacement versions.
 */
class AemsEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('workingPaperIds') && is_string($this->input('workingPaperIds'))) {
            $decoded = json_decode((string) $this->input('workingPaperIds'), true);
            $this->merge(['workingPaperIds' => is_array($decoded) ? $decoded : null]);
        }

        foreach (['title', 'sourceDescription', 'custodianName', 'changeReason'] as $field) {
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
        $categoryListId = MasterList::query()
            ->where('code', 'AEMS_EVIDENCE_CATEGORY')
            ->value('id');
        $sourceListId = MasterList::query()
            ->where('code', 'AEMS_EVIDENCE_SOURCE_TYPE')
            ->value('id');
        $confidentialityListId = MasterList::query()
            ->where('code', 'DOCUMENT_CONFIDENTIALITY')
            ->value('id');

        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'evidenceCategoryId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')
                    ->where('master_list_id', $categoryListId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'evidenceSourceTypeId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')
                    ->where('master_list_id', $sourceListId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'sourceDescription' => ['required', 'string', 'min:3', 'max:5000'],
            'dateObtained' => ['required', 'date', 'before_or_equal:today'],
            'custodianName' => ['nullable', 'string', 'max:255'],
            'custodianOfficeId' => [
                'nullable',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'confidentialityLevelId' => [
                'required',
                'integer',
                Rule::exists('master_list_items', 'id')
                    ->where('master_list_id', $confidentialityListId)
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'workingPaperIds' => ['sometimes', 'array', 'max:100'],
            'workingPaperIds.*' => ['integer', 'distinct'],
            'acquisitionMethod' => ['nullable', 'string', 'max:50'],
            'acquisitionForm' => ['nullable', 'string', 'max:40'],
            'planningObjectiveId' => ['nullable', 'integer', Rule::exists('aems_planning_objectives', 'id')],
            'riskMatrixItemId' => ['nullable', 'integer', Rule::exists('aems_risk_matrix_items', 'id')],
            'controlReference' => ['nullable', 'string', 'max:160'],
            'changeReason' => [
                $this->route('evidence') ? 'required' : 'nullable',
                'string',
                'min:5',
                'max:4000',
            ],
            'lockVersion' => [
                $this->route('evidence') ? 'required' : 'nullable',
                'integer',
                'min:1',
            ],
            'file' => [
                'required',
                'file',
                'max:'.app(RuntimeConfiguration::class)->documentUploadMaxKilobytes(),
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpg,jpeg,png',
            ],
        ];
    }
}
