<?php

namespace App\Http\Requests\Iap;

use App\Models\IapBaicsIntegration;
use Illuminate\Foundation\Http\FormRequest;

class IapBaicsIntegrationRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'consumerType' => ['required', 'string', 'in:'.implode(',', IapBaicsIntegration::CONSUMER_TYPES)],
            'consumerId' => ['required', 'integer', 'min:1'],
            'decisionType' => ['required', 'string', 'in:'.implode(',', IapBaicsIntegration::DECISION_TYPES)],
            'reportId' => ['nullable', 'integer', 'exists:iap_baics_reports,id'],
            'reportVersionId' => ['nullable', 'integer', 'exists:iap_baics_report_versions,id'],
            'decisionReason' => ['nullable', 'string', 'max:10000'],
            'legacyReason' => ['nullable', 'string', 'max:10000'],
            'compensatingSource' => ['nullable', 'string', 'max:10000'],
            'authorityUserId' => ['nullable', 'integer', 'exists:users,id'],
            'reviewerId' => ['required', 'integer', 'exists:users,id'],
            'expiresAt' => ['nullable', 'date', 'after:today'],
            'lockVersion' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
