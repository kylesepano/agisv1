<?php

namespace App\Console\Commands;

use App\Services\RuntimeConfiguration;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Performs a read-only ARMIS production deployment preflight.
 *
 * The command never changes configuration or data. Render invokes it only
 * when ARMIS_DEPLOYMENT_CHECK=true after migrations and approved seeders have
 * completed successfully.
 */
class ArmisDeploymentCheckCommand extends Command
{
    protected $signature = 'armis:deployment-check
        {--strict : Treat all deployment warnings as blocking failures}';

    protected $description = 'Verify ARMIS migration, provider, storage, and production deployment prerequisites';

    /** @var list<string> */
    private const REQUIRED_MIGRATIONS = [
        '2026_08_11_000000_create_armis_foundation_tables',
        '2026_08_12_000000_harden_armis_competency_certification',
        '2026_08_13_000000_harden_armis_planning_tables',
        '2026_08_14_000000_create_armis_assignment_actuals_tables',
        '2026_08_15_000000_create_armis_report_tables',
        '2026_08_16_000000_add_armis_provider_mode_configuration',
        '2026_08_17_000000_create_armis_provider_reconciliation_tables',
        '2026_08_18_000000_create_armis_provider_monitoring_checks_table',
    ];

    public function handle(RuntimeConfiguration $runtime): int
    {
        $strict = (bool) $this->option('strict');
        $checks = [
            'Application key' => $this->applicationKeyCheck(),
            'Debug mode' => $this->debugCheck(),
            'Application URL' => $this->applicationUrlCheck(),
            'Database driver' => $this->databaseDriverCheck(),
            'ARMIS migrations' => $this->migrationCheck(),
            'ARMIS provider authority' => $this->providerCheck($runtime),
            'Private storage' => $this->storageCheck(),
            'Writable runtime directories' => $this->runtimeDirectoryCheck(),
            'Configuration cache' => $this->configurationCacheCheck(),
        ];

        $blockingFailures = 0;
        $this->line($strict ? 'ARMIS deployment preflight (strict)' : 'ARMIS deployment preflight');

        foreach ($checks as $label => $check) {
            [$passed, $message] = $check;
            if ($passed) {
                $this->info("PASS  {$label}: {$message}");

                continue;
            }

            if ($strict) {
                $blockingFailures++;
                $this->error("FAIL  {$label}: {$message}");
            } else {
                $this->warn("WARN  {$label}: {$message}");
            }
        }

        if ($blockingFailures > 0) {
            $this->error("ARMIS deployment preflight failed with {$blockingFailures} blocking check(s).");

            return self::FAILURE;
        }

        $this->info('ARMIS deployment preflight completed without blocking failures.');

        return self::SUCCESS;
    }

    /** @return array{bool, string} */
    private function applicationKeyCheck(): array
    {
        return [
            is_string(config('app.key')) && trim((string) config('app.key')) !== '',
            'APP_KEY is configured.',
        ];
    }

    /** @return array{bool, string} */
    private function debugCheck(): array
    {
        return [
            config('app.debug') !== true,
            config('app.debug') === true
                ? 'APP_DEBUG must be false for a deployed service.'
                : 'Application debug output is disabled.',
        ];
    }

    /** @return array{bool, string} */
    private function applicationUrlCheck(): array
    {
        $url = trim((string) config('app.url'));
        $https = str_starts_with(strtolower($url), 'https://');

        return [$https, $https ? 'APP_URL uses HTTPS.' : 'APP_URL must use HTTPS behind the Render proxy.'];
    }

    /** @return array{bool, string} */
    private function databaseDriverCheck(): array
    {
        try {
            $driver = DB::connection()->getDriverName();
        } catch (Throwable $exception) {
            return [false, 'The configured database connection could not be opened.'];
        }

        return [
            $driver === 'pgsql',
            $driver === 'pgsql'
                ? 'PostgreSQL is active.'
                : "{$driver} is active; deployed ARMIS services require PostgreSQL.",
        ];
    }

    /** @return array{bool, string} */
    private function migrationCheck(): array
    {
        try {
            if (! Schema::hasTable('migrations')) {
                return [false, 'The migration repository table does not exist.'];
            }

            $applied = DB::table('migrations')
                ->whereIn('migration', self::REQUIRED_MIGRATIONS)
                ->pluck('migration')
                ->all();
            $missing = array_values(array_diff(self::REQUIRED_MIGRATIONS, $applied));

            return [
                $missing === [],
                $missing === []
                    ? count(self::REQUIRED_MIGRATIONS).' ARMIS migrations are applied.'
                    : 'Missing migrations: '.implode(', ', $missing),
            ];
        } catch (Throwable $exception) {
            return [false, 'The migration repository could not be inspected.'];
        }
    }

    /** @return array{bool, string} */
    private function providerCheck(RuntimeConfiguration $runtime): array
    {
        try {
            $mode = $runtime->armisProviderMode();
            if ($mode !== 'ARMIS_AUTHORITATIVE') {
                return [true, "Provider mode is {$mode}; ARMIS authority is not implicitly enabled."];
            }

            if (! Schema::hasTable('armis_provider_authority_decisions')) {
                return [false, 'Authoritative mode is configured but its decision ledger is missing.'];
            }

            $activated = DB::table('armis_provider_authority_decisions')
                ->where('to_mode', 'ARMIS_AUTHORITATIVE')
                ->exists();

            return [
                $activated,
                $activated
                    ? 'Provider mode is ARMIS_AUTHORITATIVE; authoritative mode has an immutable activation decision.'
                    : 'Provider mode is ARMIS_AUTHORITATIVE but has no immutable activation decision.',
            ];
        } catch (Throwable $exception) {
            return [false, 'The ARMIS provider mode could not be verified.'];
        }
    }

    /** @return array{bool, string} */
    private function storageCheck(): array
    {
        $default = (string) config('filesystems.default');
        $disk = config("filesystems.disks.{$default}", []);
        $public = $default === 'public' || (($disk['visibility'] ?? null) === 'public');
        $privateRoot = (string) config('filesystems.disks.local.root');

        return [
            ! $public && $privateRoot !== '',
            $public
                ? 'The default document disk must not be publicly visible.'
                : "Default disk is {$default}; private document root is configured.",
        ];
    }

    /** @return array{bool, string} */
    private function runtimeDirectoryCheck(): array
    {
        $writable = is_writable(storage_path()) && is_writable(base_path('bootstrap/cache'));

        return [
            $writable,
            $writable
                ? 'Storage and bootstrap/cache are writable.'
                : 'Storage and bootstrap/cache must be writable by the web process.',
        ];
    }

    /** @return array{bool, string} */
    private function configurationCacheCheck(): array
    {
        $cached = method_exists(app(), 'configurationIsCached') && app()->configurationIsCached();

        return [
            $cached,
            $cached
                ? 'Laravel configuration cache is active.'
                : 'Configuration cache is not active; run php artisan config:cache before production startup.',
        ];
    }
}
