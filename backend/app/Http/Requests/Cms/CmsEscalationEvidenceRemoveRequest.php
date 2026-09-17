<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsEscalationEvidenceRemoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['lockVersion' => ['required', 'integer', 'min:1'], 'reason' => ['required', 'string', 'max:5000']];
    }
}
