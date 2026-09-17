<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;

class IapBaicsComponentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'conclusion' => ['required', 'string', 'max:20000'],
            'supportingSummary' => ['nullable', 'string', 'max:20000'],
            'limitations' => ['nullable', 'string', 'max:10000'],
            'assessorId' => ['nullable', 'integer', 'exists:users,id'],
            'reviewerId' => ['required', 'integer', 'exists:users,id'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
