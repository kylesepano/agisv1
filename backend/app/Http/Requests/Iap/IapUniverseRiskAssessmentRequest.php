<?php

namespace App\Http\Requests\Iap;

use App\Models\IapUniverseRiskAssessment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates subject-centered risk scores and their assessment-period linkage.
 */
class IapUniverseRiskAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var IapUniverseRiskAssessment|null $assessment */
        $assessment = $this->route('assessment');

        return [
            'auditUniverseItemId' => [
                'required',
                'integer',
                'exists:iap_audit_universe_items,id',
            ],
            'assessmentDate' => ['required', 'date'],
            'controlEffectivenessPercent' => ['required', 'numeric', 'min:0', 'max:100'],
            'controlEffectivenessNotes' => ['required', 'string', 'max:10000'],
            'justification' => ['required', 'string', 'max:10000'],
            'evidenceSummary' => ['nullable', 'string', 'max:10000'],
            'scores' => ['required', 'array', 'min:1'],
            'scores.*.criterionId' => [
                'required',
                'integer',
                'distinct',
                'exists:master_list_items,id',
            ],
            'scores.*.rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'scores.*.comment' => ['nullable', 'string', 'max:3000'],
            'lockVersion' => [
                $assessment ? 'required' : 'sometimes',
                'integer',
                'min:1',
            ],
        ];
    }
}
