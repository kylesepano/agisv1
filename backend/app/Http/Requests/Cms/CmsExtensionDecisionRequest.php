<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsExtensionDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'decisionComment' => ['required_without:rejectionReason', 'string', 'min:3', 'max:10000'],
            'rejectionReason' => ['required_without:decisionComment', 'string', 'min:3', 'max:10000'],
            'confirmation' => ['required', 'accepted'],
            'overrideReason' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
