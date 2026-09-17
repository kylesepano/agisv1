<?php

namespace App\Providers;

use App\Contracts\Aems\CmsRecommendationGateway;
use App\Contracts\Aems\EngagementRetentionProvider;
use App\Contracts\Aems\IapEngagementGateway;
use App\Contracts\Aems\ResourcePlanningGateway;
use App\Integrations\Aems\DatabaseCmsRecommendationGateway;
use App\Integrations\Aems\DatabaseIapEngagementGateway;
use App\Integrations\Aems\ArmisResourcePlanningGateway;
use App\Integrations\Aems\ConfigurableResourcePlanningGateway;
use App\Integrations\Aems\InterimAemsRetentionProvider;
use App\Integrations\Aems\InterimIapResourcePlanningGateway;
use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\AuditFinding;
use App\Models\AuditIssue;
use App\Models\AuditReport;
use App\Models\EntryConference;
use App\Models\ExitConference;
use App\Models\WorkingPaper;
use App\Policies\AuditEngagementPolicy;
use App\Policies\AuditEvidencePolicy;
use App\Policies\AuditFindingPolicy;
use App\Policies\AuditIssuePolicy;
use App\Policies\AuditReportPolicy;
use App\Policies\EntryConferencePolicy;
use App\Policies\ExitConferencePolicy;
use App\Policies\WorkingPaperPolicy;
use App\Services\RuntimeConfiguration;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Registers application-wide framework customizations and boot-time behavior.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(IapEngagementGateway::class, DatabaseIapEngagementGateway::class);
        $this->app->bind(CmsRecommendationGateway::class, DatabaseCmsRecommendationGateway::class);
        $this->app->singleton(ArmisResourcePlanningGateway::class);
        $this->app->singleton(InterimIapResourcePlanningGateway::class);
        $this->app->bind(ResourcePlanningGateway::class, ConfigurableResourcePlanningGateway::class);
        $this->app->bind(EngagementRetentionProvider::class, InterimAemsRetentionProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Explicit policy registration keeps AEMS authorization discoverable and testable.
        Gate::policy(AuditEngagement::class, AuditEngagementPolicy::class);
        Gate::policy(AuditEvidence::class, AuditEvidencePolicy::class);
        Gate::policy(AuditFinding::class, AuditFindingPolicy::class);
        Gate::policy(AuditIssue::class, AuditIssuePolicy::class);
        Gate::policy(AuditReport::class, AuditReportPolicy::class);
        Gate::policy(EntryConference::class, EntryConferencePolicy::class);
        Gate::policy(WorkingPaper::class, WorkingPaperPolicy::class);
        Gate::policy(ExitConference::class, ExitConferencePolicy::class);

        RateLimiter::for('login', function (Request $request) {
            $configuration = app(RuntimeConfiguration::class);
            $employeeId = Str::upper(trim((string) $request->input('employeeId')));
            $ipAddress = $request->ip();
            $blockedResponse = fn () => response()->json([
                'success' => false,
                'message' => 'Too many sign-in attempts. Please wait one minute and try again.',
            ], 429);

            // Browser regression tests authenticate many isolated contexts in a
            // single worker. Keep the production controls unchanged while
            // allowing the test server to exercise the actual login flow
            // without exhausting the production-sized per-minute buckets.
            if (filter_var(env('AGIS_E2E_BROWSER', false), FILTER_VALIDATE_BOOLEAN)) {
                return [
                    Limit::perMinute(1000)->by('login-test-user:'.$employeeId.'|'.$ipAddress),
                    Limit::perMinute(1000)->by('login-test-ip:'.$ipAddress),
                ];
            }

            return [
                Limit::perMinute($configuration->failedLoginLimit())
                    ->by('login-user:'.$employeeId.'|'.$ipAddress)
                    ->response($blockedResponse),
                Limit::perMinute(30)
                    ->by('login-ip:'.$ipAddress)
                    ->response($blockedResponse),
            ];
        });

        $aisKey = static fn (Request $request): string => 'ais-user:' . (string) ($request->user()?->id ?? 'guest') . '|ip:' . (string) $request->ip();
        $aisBlockedResponse = static fn () => response()->json([
            'success' => false,
            'message' => 'AIS request limit reached. Please retry shortly.',
        ], 429);

        RateLimiter::for('ais-read', function (Request $request) use ($aisKey, $aisBlockedResponse) {
            return Limit::perMinute(max(1, (int) config('ais.read_rate_limit', 120)))
                ->by($aisKey($request))
                ->response($aisBlockedResponse);
        });

        RateLimiter::for('ais-generate', function (Request $request) use ($aisKey, $aisBlockedResponse) {
            return Limit::perMinute(max(1, (int) config('ais.generate_rate_limit', 12)))
                ->by($aisKey($request))
                ->response($aisBlockedResponse);
        });

        RateLimiter::for('ais-export', function (Request $request) use ($aisKey, $aisBlockedResponse) {
            return Limit::perMinute(max(1, (int) config('ais.export_rate_limit', 20)))
                ->by($aisKey($request))
                ->response($aisBlockedResponse);
        });
    }
}
