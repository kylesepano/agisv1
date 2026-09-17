<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Requires a reason when cancelling a schedule without deleting its history.
 */
class IapScheduleCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
