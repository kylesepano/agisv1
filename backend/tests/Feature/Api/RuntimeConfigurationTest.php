<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\RuntimeConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RuntimeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_public_runtime_configuration_exposes_safe_effective_values(): void
    {
        $this->getJson('/api/runtime-configuration')
            ->assertOk()
            ->assertJsonPath('data.configuration.systemShortName', 'AGIS')
            ->assertJsonPath('data.configuration.paginationSize', 25)
            ->assertJsonPath('data.configuration.passwordMinLength', 8)
            ->assertJsonPath('data.configuration.documentUploadMaxMb', 25)
            ->assertJsonPath('data.configuration.notificationRefreshSeconds', 60);
    }

    public function test_configuration_updates_apply_immediately_to_runtime_and_pagination(): void
    {
        $administrator = User::query()->where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($administrator);

        $this->putJson('/api/system-configurations', [
            'configurations' => [
                ['key' => 'system_short_name', 'value' => 'AGIS Test'],
                ['key' => 'pagination_size', 'value' => 37],
                ['key' => 'session_timeout_minutes', 'value' => 45],
                ['key' => 'fiscal_year_start_month', 'value' => 8],
                ['key' => 'iap_default_annual_person_days', 'value' => 210],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.configuration.systemShortName', 'AGIS Test')
            ->assertJsonPath('data.configuration.paginationSize', 37)
            ->assertJsonPath('data.configuration.sessionTimeoutMinutes', 45)
            ->assertJsonPath('data.configuration.iapDefaultAnnualPersonDays', 210);

        $this->assertSame(45, config('session.lifetime'));
        $this->assertSame(37, app(RuntimeConfiguration::class)->paginationSize());
        $this->assertSame(210, app(RuntimeConfiguration::class)->integer('iap_default_annual_person_days'));

        $this->getJson('/api/runtime-configuration')
            ->assertOk()
            ->assertJsonPath('data.configuration.systemShortName', 'AGIS Test');

        $this->getJson('/api/iap/plans')
            ->assertOk()
            ->assertJsonPath('data.pagination.perPage', 37);

        $this->assertTrue(
            ActivityLog::query()->where('action', 'system_configuration.updated')->exists(),
        );
        $this->assertTrue(
            AuditLog::query()->where('action', 'system_configuration.updated')->exists(),
        );
    }

    public function test_security_configuration_controls_password_validation_and_lock_duration(): void
    {
        $administrator = User::query()->where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($administrator);

        $this->putJson('/api/system-configurations', [
            'configurations' => [
                ['key' => 'password_min_length', 'value' => 12],
                ['key' => 'failed_login_limit', 'value' => 2],
                ['key' => 'account_lock_minutes', 'value' => 30],
            ],
        ])->assertOk();

        $this->putJson('/api/profile/password', [
            'currentPassword' => 'lala',
            'password' => 'too-short',
            'password_confirmation' => 'too-short',
        ])->assertUnprocessable();

        auth()->forgetGuards();
        $administrator->forceFill([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();

        foreach (range(1, 2) as $attempt) {
            $this->postJson('/api/login', [
                'employeeId' => $administrator->employee_id,
                'password' => 'incorrect',
            ])->assertUnprocessable();
        }

        $locked = $administrator->fresh();
        $this->assertTrue($locked->isLocked());
        $this->assertSame(2, $locked->failed_login_attempts);
        $this->assertTrue($locked->locked_until->between(now()->addMinutes(29), now()->addMinutes(31)));
    }

    public function test_invalid_runtime_values_are_rejected_without_poisoning_the_cache(): void
    {
        $administrator = User::query()->where('username', 'admin')->firstOrFail();
        Sanctum::actingAs($administrator);

        $this->putJson('/api/system-configurations', [
            'configurations' => [
                ['key' => 'pagination_size', 'value' => 1000],
            ],
        ])->assertUnprocessable();

        $this->putJson('/api/system-configurations', [
            'configurations' => [
                ['key' => 'timezone', 'value' => 'Invalid/Timezone'],
            ],
        ])->assertUnprocessable();

        $this->assertSame(
            25,
            (int) SystemConfiguration::query()->where('key', 'pagination_size')->value('value'),
        );
        $this->assertSame(25, app(RuntimeConfiguration::class)->paginationSize());
    }
}
