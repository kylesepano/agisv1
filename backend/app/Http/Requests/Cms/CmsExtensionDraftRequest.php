<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsExtensionDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'requestedTargetDate' => ['required', 'date'],
            'extensionJustification' => ['nullable', 'string', 'max:10000'],
            'causeOfDelay' => ['nullable', 'string', 'max:10000'],
            'actionsAlreadyTaken' => ['nullable', 'string', 'max:10000'],
            'remainingActions' => ['nullable', 'string', 'max:10000'],
            'recoveryPlan' => ['nullable', 'string', 'max:10000'],
            'impactIfNotApproved' => ['nullable', 'string', 'max:10000'],
            'revisedScheduleSummary' => ['nullable', 'string', 'max:10000'],
            'managementProgressSummary' => ['nullable', 'string', 'max:10000'],
            'noEvidenceExplanation' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
