<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validates the BAICS cycle foundation and its explicit source scope. */
class IapBaicsAssessmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }

    public function rules(): array
    {
        $assessment = $this->route('assessment');
        return [
            'assessmentCode' => ['nullable', 'string', 'max:80', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/', Rule::unique('iap_baics_assessments', 'assessment_code')->ignore($assessment?->id)->whereNull('deleted_at')],
            'assessmentYear' => ['required', 'integer', 'min:2000', 'max:2200'],
            'name' => ['required', 'string', 'max:255'],
            'responsibleOfficeId' => ['required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'scopeSummary' => ['required', 'string', 'max:10000'],
            'objectives' => ['required', 'string', 'max:10000'],
            'boundaries' => ['nullable', 'string', 'max:10000'],
            'exclusions' => ['nullable', 'string', 'max:10000'],
            'limitations' => ['nullable', 'string', 'max:10000'],
            'methodology' => ['nullable', 'string', 'max:10000'],
            'plannedStartDate' => ['nullable', 'date'],
            'plannedEndDate' => ['nullable', 'date', 'after_or_equal:plannedStartDate'],
            'reviewDate' => ['nullable', 'date'],
            'reportDate' => ['nullable', 'date'],
            'legacyStatus' => ['nullable', 'in:LEGACY,EXEMPT,REASSESSMENT_REQUIRED'],
            'legacyReason' => ['nullable', 'required_with:legacyStatus', 'string', 'max:5000'],
            'legacyAuthorityUserId' => ['nullable', 'integer', 'exists:users,id'],
            'legacyExpiresAt' => ['nullable', 'date'],
            'scopeItems' => ['required', 'array', 'min:1', 'max:500'],
            'scopeItems.*.auditUniverseItemId' => ['required', 'integer', 'distinct', Rule::exists('iap_audit_universe_items', 'id')->whereNull('deleted_at')],
            'scopeItems.*.officeId' => ['required', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'scopeItems.*.auditAreaId' => ['required', 'integer', Rule::exists('audit_areas', 'id')->whereNull('deleted_at')],
            'scopeItems.*.auditFocusId' => ['required', 'integer', Rule::exists('audit_focuses', 'id')->whereNull('deleted_at')],
            'scopeItems.*.scopeNotes' => ['nullable', 'string', 'max:5000'],
            'scopeItems.*.boundaries' => ['nullable', 'string', 'max:5000'],
            'scopeItems.*.exclusions' => ['nullable', 'string', 'max:5000'],
            'scopeItems.*.limitations' => ['nullable', 'string', 'max:5000'],
            'lockVersion' => [$assessment ? 'required' : 'sometimes', 'integer', 'min:1'],
        ];
    }
}
