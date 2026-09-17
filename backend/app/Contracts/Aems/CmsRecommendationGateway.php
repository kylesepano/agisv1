<?php

namespace App\Contracts\Aems;

use App\Models\AuditEngagement;
use App\Models\AuditRecommendation;
use App\Models\AuditReport;
use App\Models\AuditReportVersion;
use App\Models\CmsRecommendation;
use Illuminate\Http\Request;

/** Idempotent AEMS-to-CMS recommendation intake boundary. */
interface CmsRecommendationGateway
{
    public function transfer(
        AuditRecommendation $recommendation,
        AuditEngagement $engagement,
        AuditReport $report,
        AuditReportVersion $version,
        Request $request,
    ): CmsRecommendation;

    /** @return array<string, mixed> */
    public function status(): array;
}
