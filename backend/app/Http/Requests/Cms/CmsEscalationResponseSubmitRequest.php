<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsEscalationResponseSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['lockVersion' => ['required', 'integer', 'min:1'], 'confirmation' => ['accepted']];
    }
}
