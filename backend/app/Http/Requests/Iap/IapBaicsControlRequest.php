<?php

namespace App\Http\Requests\Iap;

use App\Models\IapBaicsControl;
use Illuminate\Foundation\Http\FormRequest;

class IapBaicsControlRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'scopeItemId' => ['required', 'integer', 'exists:iap_baics_scope_items,id'],
            'componentId' => ['nullable', 'integer', 'exists:iap_baics_components,id'],
            'controlCode' => ['required', 'string', 'max:100'],
            'processStep' => ['required', 'string', 'max:255'],
            'responsibleUnit' => ['nullable', 'string', 'max:255'],
            'controlOwnerOfficeId' => ['required', 'integer', 'exists:offices,id'],
            'controlOwnerUserId' => ['nullable', 'integer', 'exists:users,id'],
            'objective' => ['required', 'string', 'max:20000'],
            'relatedRisk' => ['required', 'string', 'max:20000'],
            'controlDescription' => ['required', 'string', 'max:20000'],
            'expectedResult' => ['required', 'string', 'max:20000'],
            'controlType' => ['required', 'in:'.implode(',', IapBaicsControl::TYPES)],
            'executionMode' => ['required', 'in:'.implode(',', IapBaicsControl::EXECUTION_MODES)],
            'frequency' => ['nullable', 'string', 'max:100'],
            'evidenceProduced' => ['nullable', 'string', 'max:20000'],
            'approvalRequired' => ['boolean'],
            'segregationOfDutiesRequired' => ['boolean'],
            'designAssessment' => ['required', 'string', 'max:20000'],
            'operatingAssessment' => ['required', 'string', 'max:20000'],
            'controlStatus' => ['required', 'in:'.implode(',', IapBaicsControl::CONTROL_STATUSES)],
            'deficiencyClassification' => ['nullable', 'in:'.implode(',', IapBaicsControl::CLASSIFICATIONS)],
            'limitationDetails' => ['nullable', 'string', 'max:20000'],
            'gapDetails' => ['nullable', 'string', 'max:20000'],
            'breakdownDetails' => ['nullable', 'string', 'max:20000'],
            'contradictionDetails' => ['nullable', 'string', 'max:20000'],
            'recommendationAction' => ['nullable', 'string', 'max:20000'],
            'reviewerId' => ['required', 'integer', 'exists:users,id'],
            'methodIds' => ['nullable', 'array'],
            'methodIds.*' => ['integer', 'exists:iap_baics_methods,id'],
            'evidenceLinkIds' => ['nullable', 'array'],
            'evidenceLinkIds.*' => ['integer', 'exists:iap_baics_evidence_links,id'],
            'lockVersion' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
