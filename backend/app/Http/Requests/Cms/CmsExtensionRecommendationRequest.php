<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CmsExtensionRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'recommendationCode' => ['required', 'in:RECOMMEND_APPROVAL,RECOMMEND_REJECTION'],
            'assessmentSummary' => ['required', 'string', 'min:3', 'max:10000'],
            'evidenceReviewSummary' => ['required', 'string', 'min:3', 'max:10000'],
            'feasibilityAssessment' => ['required', 'string', 'min:3', 'max:10000'],
            'riskOfDelaySummary' => ['required', 'string', 'min:3', 'max:10000'],
            'conditionsOrObservations' => ['nullable', 'string', 'max:10000'],
            'confirmation' => ['required', 'accepted'],
        ];
    }
}
