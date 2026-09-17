<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsEscalationNoticeDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['primaryTriggerCode' => ['required', 'string', 'max:80'], 'additionalTriggerExplanation' => ['nullable', 'string', 'max:10000'], 'subject' => ['required', 'string', 'max:255'], 'escalationSummary' => ['required', 'string', 'max:20000'], 'basisAndContext' => ['required', 'string', 'max:20000'], 'requiredManagementActions' => ['required', 'string', 'max:20000'], 'requiredResponseContents' => ['required', 'string', 'max:20000'], 'responseDueDate' => ['required', 'date', 'after:today'], 'consequenceOrFollowUpStatement' => ['nullable', 'string', 'max:10000'], 'managementAttentionRequested' => ['sometimes', 'boolean'], 'lockVersion' => ['required', 'integer', 'min:1']];
    }
}
