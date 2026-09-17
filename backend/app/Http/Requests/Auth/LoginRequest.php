<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates employee-ID login credentials before authentication is attempted.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'employeeId' => mb_strtoupper(trim((string) $this->input('employeeId'))),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'employeeId' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'employeeId.required' => 'Enter your Employee ID.',
            'password.required' => 'Enter your password.',
        ];
    }
}
