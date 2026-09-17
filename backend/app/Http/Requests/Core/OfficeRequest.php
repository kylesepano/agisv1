<?php

namespace App\Http\Requests\Core;

use App\Models\Office;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates unique office identity and its sector, head, and audit-area assignments.
 */
class OfficeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'acronym' => $this->filled('acronym')
                ? strtoupper(trim((string) $this->input('acronym')))
                : null,
            'name' => trim((string) $this->input('name')),
            'description' => $this->filled('description')
                ? trim((string) $this->input('description'))
                : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var Office|null $office */
        $office = $this->route('office');

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('offices', 'code')
                    ->ignore($office?->getKey())
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'acronym' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sector' => ['nullable', 'string', 'max:120'],
            'contactNumber' => ['nullable', 'string', 'max:255'],
            'officeTypeId' => [
                'nullable',
                'integer',
                Rule::exists('master_list_items', 'id')->where('is_active', true),
            ],
            'headUserId' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'auditAreaIds' => ['sometimes', 'array'],
            'auditAreaIds.*' => [
                'integer',
                Rule::exists('audit_areas', 'id')->whereNull('deleted_at'),
            ],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
