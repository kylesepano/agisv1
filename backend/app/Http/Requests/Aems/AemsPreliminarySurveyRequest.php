<?php

namespace App\Http\Requests\Aems;

use App\Models\ArmisResourceProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AemsPreliminarySurveyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'section' => ['required', Rule::in(['meeting', 'auditee', 'ppsp', 'ics'])],
            'lockVersion' => ['present', 'nullable', 'integer', 'min:1'],
            'data' => ['present', 'array'],
        ];
        $fields = match ($this->input('section')) {
            'meeting' => ['proposedAt', 'venue', 'focalPerson', 'ciasParticipants', 'auditeeParticipants', 'mattersToDiscuss', 'documentsNeeded', 'actualDate', 'mattersDiscussed'],
            'auditee' => ['mandate', 'mission', 'vision', 'objectives', 'strategicGoals'],
            'ppsp' => ['mandate', 'purpose', 'scope', 'expectedResults', 'responsibleUnit', 'targetUsers', 'resources', 'measures'],
            default => [],
        };
        foreach ($fields as $field) {
            $rules["data.$field"] = ['nullable', 'string', 'max:10000'];
        }
        if ($this->input('section') === 'meeting') {
            $rules['data.ciasParticipantIds'] = ['nullable', 'array', 'max:100'];
            $rules['data.ciasParticipantIds.*'] = ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')->where(fn ($query) => $query->where('is_active', true)->whereIn('id', ArmisResourceProfile::query()->where('status', 'ACTIVE')->select('user_id')))];
            $rules['data.proposedAt'] = ['nullable', 'date'];
            $rules['data.actualDate'] = ['nullable', 'date'];
            $rules['data.noticeDocumentVersionId'] = ['nullable', 'integer', 'exists:document_versions,id'];
        }
        if ($this->input('section') === 'auditee') {
            foreach (['annualBudget', 'personnelBudget', 'mooeBudget', 'capitalBudget', 'sppBudget'] as $field) {
                $rules["data.$field"] = ['nullable', 'numeric', 'min:0'];
            }
            $rules['data.budgetDocumentVersionId'] = ['nullable', 'integer', 'exists:document_versions,id'];
            $this->rows($rules, 'programs', ['title', 'objectives', 'beneficiaries', 'fundingSource', 'budget', 'implementationPeriod', 'collaborators', 'status']);
            $rules['data.programs.*.budget'] = ['nullable', 'numeric', 'min:0'];
        }
        if ($this->input('section') === 'ppsp') {
            $this->rows($rules, 'steps', ['activity', 'responsible', 'inputOutput', 'controlRecord', 'source']);
            $this->rows($rules, 'sources', ['documentPersonMethod', 'dateCustodian', 'supportsLimitation']);
            $this->rows($rules, 'questions', ['informationGap', 'verificationNeeded', 'ownerDue', 'taskLink']);
            $this->rows($rules, 'risks', ['riskDescription', 'riskCategory', 'likelihood', 'likelihoodJustification', 'impact', 'impactJustification', 'controlActivity', 'controlType', 'controlOwner', 'controlFrequency', 'operatingEffectiveness', 'residualRiskRating']);
            foreach (['likelihood', 'impact'] as $field) {
                $rules["data.risks.*.$field"] = ['nullable', 'integer', 'between:1,5'];
            }
            $rules['data.flowchartDocumentVersionId'] = ['nullable', 'integer', 'exists:document_versions,id'];
        }
        if ($this->input('section') === 'ics') {
            $this->rows($rules, 'assessments', ['preliminaryView', 'basisSource', 'keyMatters', 'preliminaryAssessment', 'nextAction']);
            $rules['data.assessments'] = ['nullable', 'array', 'max:16'];
            $rules['data.assessments.*.principleId'] = ['required', 'integer', 'between:1,16', 'distinct'];
        }

        return $rules;
    }

    private function rows(array &$rules, string $key, array $fields): void
    {
        $rules["data.$key"] = ['nullable', 'array', 'max:500'];
        $rules["data.$key.*"] = ['array'];
        foreach ($fields as $field) {
            $rules["data.$key.*.$field"] = ['nullable', 'string', 'max:10000'];
        }
    }
}
