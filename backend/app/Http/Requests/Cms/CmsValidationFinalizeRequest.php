<?php

namespace App\Http\Requests\Cms;

use App\Models\CmsValidationVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CmsValidationFinalizeRequest extends FormRequest
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
            'finalConclusionCode' => ['required', Rule::in(CmsValidationVersion::CONCLUSIONS)],
            'finalizationComment' => ['required', 'string', 'max:10000'],
            'confirmation' => ['required', 'accepted'],
            'overrideReason' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
