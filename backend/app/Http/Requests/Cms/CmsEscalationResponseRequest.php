<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsEscalationResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['managementResponseSummary' => ['required', 'string', 'max:20000'], 'rootCauseOrExplanation' => ['required', 'string', 'max:20000'], 'actionsCompleted' => ['required', 'string', 'max:20000'], 'remainingActions' => ['required', 'string', 'max:20000'], 'committedActions' => ['required', 'string', 'max:20000'], 'responsiblePersonOrOffice' => ['required', 'string', 'max:255'], 'commitmentStartDate' => ['nullable', 'date'], 'commitmentTargetDate' => ['required', 'date', 'after_or_equal:commitmentStartDate'], 'resourceOrDependencyNeeds' => ['nullable', 'string', 'max:10000'], 'requestForCiasGuidance' => ['nullable', 'string', 'max:10000'], 'noEvidenceExplanation' => ['nullable', 'string', 'max:10000'], 'lockVersion' => ['required', 'integer', 'min:1']];
    }
}
