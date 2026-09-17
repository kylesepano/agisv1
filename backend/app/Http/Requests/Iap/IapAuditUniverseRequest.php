<?php

namespace App\Http\Requests\Iap;

use App\Models\IapAuditUniverseItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates auditable-subject ownership, classification, exposure, and status data.
 */
class IapAuditUniverseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subjectCode' => strtoupper(trim((string) $this->input('subjectCode'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var IapAuditUniverseItem|null $auditUniverse */
        $auditUniverse = $this->route('auditUniverse');

        return [
            'subjectCode' => [
                'required',
                'string',
                'max:60',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('iap_audit_universe_items', 'subject_code')
                    ->ignore($auditUniverse?->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'subjectTypeId' => ['required', 'integer', 'exists:master_list_items,id'],
            'responsibleOfficeId' => [
                'required',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'primaryAuditAreaId' => [
                'required',
                'integer',
                Rule::exists('audit_areas', 'id')->whereNull('deleted_at'),
            ],
            'materialityLevelId' => ['nullable', 'integer', 'exists:master_list_items,id'],
            'description' => ['required', 'string', 'max:10000'],
            'auditScope' => ['nullable', 'string', 'max:10000'],
            'materialityExposure' => ['nullable', 'string', 'max:10000'],
            'lastAuditDate' => ['nullable', 'date', 'before_or_equal:today'],
            'historicalAuditSummary' => ['nullable', 'string', 'max:10000'],
            'stakeholderOfficeIds' => ['sometimes', 'array'],
            'stakeholderOfficeIds.*' => [
                'integer',
                'distinct',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'isActive' => ['sometimes', 'boolean'],
            'lockVersion' => [$auditUniverse ? 'required' : 'sometimes', 'integer', 'min:1'],
        ];
    }
}
