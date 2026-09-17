<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/** Validates non-destructive ending of a Compliance Monitor assignment. */
class CmsMonitorAssignmentEndRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
