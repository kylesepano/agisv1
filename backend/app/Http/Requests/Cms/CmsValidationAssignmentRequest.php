<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsValidationAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'validatorUserId' => ['required', 'integer', 'exists:users,id'],
            'assignmentReason' => ['required', 'string', 'max:5000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
