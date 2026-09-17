<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArmisDeploymentHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_armis_deployment_check_reports_applied_migrations_and_safe_provider_defaults(): void
    {
        $this->artisan('armis:deployment-check')
            ->expectsOutputToContain('8 ARMIS migrations are applied.')
            ->expectsOutputToContain('Provider mode is ARMIS_AUTHORITATIVE')
            ->assertExitCode(0);
    }

    public function test_strict_armis_deployment_check_rejects_debug_and_insecure_url(): void
    {
        config([
            'app.debug' => true,
            'app.url' => 'http://localhost',
        ]);

        $this->artisan('armis:deployment-check', ['--strict' => true])
            ->expectsOutputToContain('ARMIS deployment preflight failed')
            ->assertExitCode(1);
    }

    public function test_render_startup_and_apache_keep_migrations_and_private_downloads_hardened(): void
    {
        $root = dirname(base_path());
        $startup = (string) file_get_contents($root.'/docker/render-start.sh');
        $apache = (string) file_get_contents($root.'/docker/apache-vhost.conf');
        $smoke = (string) file_get_contents($root.'/scripts/verify-armis-render.ps1');

        $this->assertStringContainsString('php artisan migrate --force', $startup);
        $this->assertStringNotContainsString('migrate:fresh', $startup);
        $this->assertStringContainsString('ARMIS_DEPLOYMENT_CHECK=true', $startup);
        $this->assertStringContainsString('php artisan armis:deployment-check --strict', $startup);
        $this->assertLessThan(
            strpos($startup, 'php artisan armis:deployment-check --strict'),
            strpos($startup, 'php artisan config:cache'),
        );
        $this->assertStringContainsString('Header always set X-Content-Type-Options "nosniff"', $apache);
        $this->assertStringContainsString('Header always set Referrer-Policy', $apache);
        $this->assertStringContainsString('$BaseUrl/health', $smoke);
        $this->assertStringContainsString('$BaseUrl/api/armis/provider/status', $smoke);
        $this->assertStringContainsString('X-Content-Type-Options', $smoke);
        $this->assertFalse((bool) config('filesystems.disks.local.serve'));
    }
}
