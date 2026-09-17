<?php

namespace App\Http\Requests\Aems;

use App\Models\AuditEngagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates mutable special-engagement and registry fields; IAP snapshots are
 * populated only by the server-side importer.
 */
class AemsEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $emptyCollections = collect(['officeIds', 'auditAreaIds', 'auditFocusIds'])
            ->filter(fn (string $key): bool => $this->has($key) && empty($this->input($key)))
            ->mapWithKeys(fn (string $key): array => [$key => null])
            ->all();

        $this->merge([
            'engagementCode' => $this->filled('engagementCode')
                ? strtoupper(trim((string) $this->input('engagementCode')))
                : null,
            'title' => trim((string) $this->input('title')),
            'specialAuthorityReference' => $this->filled('specialAuthorityReference')
                ? strtoupper(trim((string) $this->input('specialAuthorityReference')))
                : null,
            ...$emptyCollections,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var AuditEngagement|null $engagement */
        $engagement = $this->route('engagement');
        $creating = $this->isMethod('post');

        return [
            'engagementCode' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('audit_engagements', 'engagement_code')
                    ->ignore($engagement?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'specialAuthorityReference' => [
                Rule::requiredIf($creating),
                'nullable',
                'string',
                'max:100',
            ],
            'specialAuthorityTypeCode' => ['nullable', 'string', 'max:60'],
            'specialAuthorityClass' => ['nullable', 'string', 'in:SPECIAL,EMERGENCY'],
            'specialAuthorityDate' => [Rule::requiredIf($creating), 'nullable', 'date'],
            'specialAuthorityApprovedBy' => [
                Rule::requiredIf($creating),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'auditTypeId' => ['nullable', 'integer', 'exists:master_list_items,id'],
            'engagementApproachId' => ['nullable', 'integer', 'exists:master_list_items,id'],
            'background' => ['nullable', 'string', 'max:10000'],
            // SCR-212 scope and coverage are maintained in the dedicated
            // Engagement Scope workspace. Registry edits must not require the
            // scope payload (and must not overwrite it with empty arrays).
            'objectives' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'scope' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'scopeBoundaries' => ['nullable', 'string', 'max:10000'],
            'scopeLimitations' => ['nullable', 'string', 'max:10000'],
            'scopeSourceVariance' => ['nullable', 'array'],
            'exclusions' => ['nullable', 'string', 'max:10000'],
            'plannedStartDate' => ['required', 'date'],
            'plannedEndDate' => ['required', 'date', 'after_or_equal:plannedStartDate'],
            'expectedReportDate' => ['nullable', 'date', 'after_or_equal:plannedEndDate'],
            'plannedPersonDays' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'officeIds' => [
                'sometimes',
                'nullable',
                'array',
                'size:1',
            ],
            'officeIds.*' => [
                'integer',
                'distinct',
                Rule::exists('offices', 'id')->whereNull('deleted_at'),
            ],
            'auditAreaIds' => ['sometimes', 'nullable', 'array', 'min:1'],
            'auditAreaIds.*' => [
                'integer',
                'distinct',
                Rule::exists('audit_areas', 'id')->whereNull('deleted_at'),
            ],
            'auditFocusIds' => ['sometimes', 'nullable', 'array'],
            'auditFocusIds.*' => [
                'integer',
                'distinct',
                Rule::exists('audit_focuses', 'id')->whereNull('deleted_at'),
            ],
            'lockVersion' => [$creating ? 'sometimes' : 'required', 'integer', 'min:1'],
        ];
    }
}
