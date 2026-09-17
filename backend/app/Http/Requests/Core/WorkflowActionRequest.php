<?php

namespace App\Http\Requests\Core;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a workflow action, expected state, comment, and optimistic version.
 */
class WorkflowActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:0'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
