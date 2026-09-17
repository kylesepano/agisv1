<?php

namespace App\Integrations\Aems;

use App\Contracts\Aems\CmsRecommendationGateway;
use App\Models\AuditEngagement;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CmsRecommendation;
use App\Models\CmsRecommendationCase;
use App\Services\Cms\CmsIntakeService;
use Illuminate\Http\Request;

/** Thin AEMS adapter over the authoritative immutable CMS intake service. */
class DatabaseCmsRecommendationGateway implements CmsRecommendationGateway
{
    public function __construct(private readonly CmsIntakeService $intake) {}

    public function transfer(
        AuditRecommendation $recommendation,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
        Request $request,
    ): CmsRecommendation {
        return $this->intake->intake(
            $recommendation,
            $engagement,
            $report,
            $version,
            $request,
        );
    }

    public function status(): array
    {
        return [
            'module' => 'CMS',
            'provider' => self::class,
            'mode' => 'IMMUTABLE_INTAKE',
            'available' => true,
            'transferredRecommendations' => CmsRecommendation::query()->count(),
            'operationalCases' => CmsRecommendationCase::query()->count(),
            'caseCoverageComplete' => ! CmsRecommendation::query()
                ->whereDoesntHave('case')
                ->exists(),
            'ownership' => 'CREATE_ONCE',
            'immutableSourceEnvelope' => true,
            'idempotency' => 'TRANSFER_KEY_AND_SOURCE_RECOMMENDATION_UNIQUE',
            'aemsSourceLinks' => ! CmsRecommendation::query()
                ->whereNull('source_snapshot')
                ->exists(),
        ];
    }
}
