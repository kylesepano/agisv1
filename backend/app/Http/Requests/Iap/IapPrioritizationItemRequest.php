<?php

namespace App\Http\Requests\Iap;

use App\Models\IapPrioritizationItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a ranked subject's selection decision and mandatory override reason.
 */
class IapPrioritizationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'finalRank' => ['required', 'integer', 'min:1'],
            'decision' => ['required', Rule::in(IapPrioritizationItem::DECISIONS)],
            'decisionReason' => ['nullable', 'string', 'max:10000'],
            'overrideReason' => ['nullable', 'string', 'max:10000'],
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
