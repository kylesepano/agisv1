<?php

namespace App\Http\Requests\Aems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Selects exactly one approved IAP engagement for transactional AEMS import. */
class AemsIapImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'iapPlanEngagementId' => [
                'required',
                'integer',
                Rule::exists('iap_plan_engagements', 'id')->whereNull('deleted_at'),
            ],
            'engagementCode' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('audit_engagements', 'engagement_code'),
            ],
            'initialTeamPlan' => ['nullable', 'array'],
            'initialTeamPlan.departmentHeadId' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'initialTeamPlan.teamLeaderId' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'initialTeamPlan.teamMemberIds' => ['nullable', 'array'],
            'initialTeamPlan.teamMemberIds.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'initialTeamPlan.supportStaffIds' => ['nullable', 'array'],
            'initialTeamPlan.supportStaffIds.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'initialTeamPlan.aeoReference' => ['nullable', 'string', 'max:80'],
            'initialTeamPlan.aeoDate' => ['nullable', 'date'],
        ];
    }
}
