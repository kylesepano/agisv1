<?php

namespace App\Http\Requests\Iap;

use Illuminate\Foundation\Http\FormRequest;

class IapBaicsReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'executiveSummary' => ['required', 'string', 'max:50000'],
            'objectivesScopeMethodology' => ['required', 'string', 'max:50000'],
            'overallFindings' => ['required', 'string', 'max:50000'],
            'controlGapSummary' => ['required', 'string', 'max:50000'],
            'recommendationsSummary' => ['nullable', 'string', 'max:50000'],
            'limitationsExceptions' => ['nullable', 'string', 'max:30000'],
            'controlIds' => ['required', 'array', 'min:1'],
            'controlIds.*' => ['integer', 'exists:iap_baics_controls,id'],
            'interimAnalysisIds' => ['nullable', 'array'],
            'interimAnalysisIds.*' => ['integer', 'exists:iap_baics_interim_analyses,id'],
            'reviewerId' => ['required', 'integer', 'exists:users,id'],
            'lockVersion' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
