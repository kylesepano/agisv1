<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsEscalationResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['lockVersion' => ['required', 'integer', 'min:1'], 'resolutionSummary' => ['required', 'string', 'max:20000'], 'basisForResolution' => ['required', 'string', 'max:20000'], 'followUpRequirements' => ['required', 'string', 'max:20000'], 'confirmation' => ['accepted']];
    }
}
