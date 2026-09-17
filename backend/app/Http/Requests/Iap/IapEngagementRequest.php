<?php

namespace App\Http\Requests\Iap;

use App\Models\IapPlanEngagement;
use App\Models\MasterListItem;
use App\Services\RuntimeConfiguration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates Annual Plan engagement scope, timing, effort, and source relationships.
 */
class IapEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var IapPlanEngagement|null $engagement */
        $engagement = $this->route('engagement');
        $this->merge([
            'engagementCode' => strtoupper(trim((string) $this->input('engagementCode'))),
            'title' => trim((string) $this->input('title')),
            'prioritizationItemId' => $this->input(
                'prioritizationItemId',
                $engagement?->prioritization_item_id,
            ),
        ]);

        if (! $this->filled('riskLevelId') && ! $this->filled('prioritizationItemId')) {
            $defaultRiskId = $engagement?->risk_level_id ?? MasterListItem::query()
                ->where('code', app(RuntimeConfiguration::class)->string('default_risk_level_code'))
                ->whereHas('masterList', fn ($query) => $query->where('code', 'RISK_LEVEL'))
                ->where('is_active', true)
                ->value('id');
            if ($defaultRiskId) {
                $this->merge(['riskLevelId' => $defaultRiskId]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var IapPlanEngagement|null $engagement */
        $engagement = $this->route('engagement');
        $plan = $this->route('plan');

        $fromPrioritization = $this->filled('prioritizationItemId')
            || $engagement?->prioritization_item_id !== null;

        return [
            'engagementCode' => [
                'required',
                'string',
                'max:60',
                Rule::unique('iap_plan_engagements', 'engagement_code')
                    ->where('plan_id', $plan?->getKey())
                    ->ignore($engagement?->getKey()),
            ],
            'title' => ['required', 'string', 'max:255'],
            'engagementTypeId' => ['required', 'integer', 'exists:master_list_items,id'],
            'auditApproachId' => [
                $fromPrioritization ? 'required' : 'nullable',
                'integer',
                'exists:master_list_items,id',
            ],
            'priorityId' => ['required', 'integer', 'exists:master_list_items,id'],
            'riskLevelId' => [
                $fromPrioritization ? 'nullable' : 'required',
                'integer',
                'exists:master_list_items,id',
            ],
            'riskAssessmentId' => ['nullable', 'integer', 'exists:iap_risk_assessments,id'],
            'prioritizationItemId' => [
                $fromPrioritization ? 'required' : 'nullable',
                'integer',
                'exists:iap_prioritization_items,id',
            ],
            'background' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['required', 'string', 'max:10000'],
            'scope' => ['required', 'string', 'max:10000'],
            'exclusions' => ['nullable', 'string', 'max:10000'],
            'auditCriteria' => ['nullable', 'string', 'max:10000'],
            'proposedMethodology' => ['nullable', 'string', 'max:10000'],
            'plannedStartDate' => ['required', 'date'],
            'plannedEndDate' => ['required', 'date', 'after_or_equal:plannedStartDate'],
            'estimatedPersonDays' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'estimatedCost' => ['nullable', 'numeric', 'min:0'],
            'targetQuarter' => [
                $fromPrioritization ? 'required' : 'nullable',
                'integer',
                'between:1,4',
            ],
            'sequenceNumber' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'planningNotes' => ['nullable', 'string', 'max:10000'],
            'officeIds' => [$fromPrioritization ? 'sometimes' : 'required', 'array', 'min:1'],
            'officeIds.*' => ['integer', 'distinct', Rule::exists('offices', 'id')->whereNull('deleted_at')],
            'auditAreaIds' => [$fromPrioritization ? 'sometimes' : 'required', 'array', 'min:1'],
            'auditAreaIds.*' => ['integer', 'distinct', Rule::exists('audit_areas', 'id')->whereNull('deleted_at')],
            'auditFocusIds' => ['sometimes', 'array'],
            'auditFocusIds.*' => ['integer', 'distinct', Rule::exists('audit_focuses', 'id')->whereNull('deleted_at')],
            'lockVersion' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
