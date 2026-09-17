<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsEscalationReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['lockVersion' => ['required', 'integer', 'min:1'], 'reviewComment' => ['nullable', 'string', 'max:5000']];
    }
}
