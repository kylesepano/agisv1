<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsEscalationAcknowledgementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['acknowledgementComment' => ['nullable', 'string', 'max:5000'], 'confirmation' => ['accepted']];
    }
}
