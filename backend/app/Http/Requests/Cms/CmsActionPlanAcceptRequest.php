<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsActionPlanAcceptRequest extends FormRequest
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
            'acceptanceComment' => ['required', 'string', 'max:5000'],
            'confirmation' => ['required', 'accepted'],
        ];
    }
}
