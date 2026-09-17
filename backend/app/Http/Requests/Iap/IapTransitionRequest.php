<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a guarded IAP lifecycle action and its supporting workflow comment.
 */
class IapTransitionRequest extends FormRequest
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
            'comment' => ['nullable', 'string', 'max:10000'],
            'completionConfirmed' => ['sometimes', 'boolean'],
        ];
    }
}
