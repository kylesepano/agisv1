<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;

class IapBaicsInterimAnalysisRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'analysisCode' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'analysisPeriodStart' => ['nullable', 'date'],
            'analysisPeriodEnd' => ['nullable', 'date', 'after_or_equal:analysisPeriodStart'],
            'analysisNarrative' => ['required', 'string', 'max:40000'],
            'findingsSummary' => ['nullable', 'string', 'max:30000'],
            'recommendationsSummary' => ['nullable', 'string', 'max:30000'],
            'limitations' => ['nullable', 'string', 'max:20000'],
            'sourceManifest' => ['nullable', 'array'],
            'sourceManifest.*' => ['array'],
            'reviewerId' => ['required', 'integer', 'exists:users,id'],
            'lockVersion' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
