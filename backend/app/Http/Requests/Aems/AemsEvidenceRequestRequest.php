<?php

namespace App\Http\Requests\Aems;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AemsEvidenceRequestRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        foreach (['title', 'purpose', 'changeReason'] as $field) {
            if ($this->has($field)) $this->merge([$field => $this->filled($field) ? trim((string) $this->input($field)) : null]);
        }
        if (is_string($this->input('requestedItems'))) {
            $decoded = json_decode($this->input('requestedItems'), true);
            $this->merge(['requestedItems' => is_array($decoded) ? $decoded : null]);
        }
    }

    public function rules(): array
    {
        $isUpdate = (bool) $this->route('request');
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'purpose' => ['required', 'string', 'min:5', 'max:10000'],
            'requestedFromOfficeId' => ['nullable', 'integer', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'requestedFromUserId' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'dueDate' => ['nullable', 'date', 'after_or_equal:today'],
            'requestedItems' => ['required', 'array', 'min:1', 'max:100'],
            'requestedItems.*' => ['string', 'min:2', 'max:1000'],
            'changeReason' => [$isUpdate ? 'required' : 'nullable', 'string', 'min:5', 'max:4000'],
            'lockVersion' => [$isUpdate ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }
}
