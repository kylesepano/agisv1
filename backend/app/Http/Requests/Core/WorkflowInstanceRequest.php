<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creation of a workflow instance for a supported module record.
 */
class WorkflowInstanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'workflowDefinitionId' => [
                'nullable',
                'required_without:moduleCode',
                'integer',
                Rule::exists('workflow_definitions', 'id')->where(
                    fn ($query) => $query
                        ->whereNull('deleted_at')
                        ->where('status', 'PUBLISHED')
                        ->where('is_active', true),
                ),
            ],
            'moduleCode' => [
                'nullable',
                'required_without:workflowDefinitionId',
                'string',
                Rule::in(['CORE', 'IAP', 'AEM', 'AFR', 'CMS', 'ARMIS', 'AIS']),
            ],
            'subjectId' => ['nullable', 'integer', 'min:1'],
            'subjectCode' => ['required', 'string', 'max:150'],
            'subjectLabel' => ['required', 'string', 'max:255'],
            'officeId' => [
                'nullable',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'context' => ['nullable', 'array'],
        ];
    }
}
