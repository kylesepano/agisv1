<?php

namespace App\Http\Requests\Core;

use App\Models\SystemNotification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates targeted notification content, recipients, navigation, and channels.
 */
class NotificationDeliveryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'targetType' => ['required', Rule::in(['USER', 'ROLE', 'OFFICE', 'ALL'])],
            'userIds' => ['required_if:targetType,USER', 'array', 'min:1'],
            'userIds.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'roleId' => [
                'required_if:targetType,ROLE',
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->whereNull('deleted_at'),
            ],
            'officeId' => [
                'required_if:targetType,OFFICE',
                'nullable',
                'integer',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'category' => ['required', Rule::in(SystemNotification::CATEGORIES)],
            'priority' => ['required', Rule::in(SystemNotification::PRIORITIES)],
            'moduleCode' => ['required', 'string', 'max:20'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'actionUrl' => ['nullable', 'string', 'max:1000', 'regex:/^\\/[A-Za-z0-9_?=&\\/-]*$/'],
            'actionLabel' => ['nullable', 'string', 'max:100'],
            'expiresAt' => ['nullable', 'date', 'after:now'],
        ];
    }
}
