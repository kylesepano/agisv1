<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Office;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\RuntimeConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformRuntimeEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Cache::flush();
    }

    public function test_runtime_branding_and_mail_settings_are_safe_and_effective(): void
    {
        Sanctum::actingAs($this->user('admin'));
        Storage::fake('public');
        Mail::fake();

        $this->putJson('/api/system-configurations', [
            'configurations' => [
                ['key' => 'document_number_format', 'value' => 'LIB-{YEAR}-{SEQ:4}'],
                ['key' => 'default_risk_level_code', 'value' => 'HIGH'],
                ['key' => 'default_workflow_sla_hours', 'value' => 48],
                ['key' => 'mail_enabled', 'value' => true],
                ['key' => 'mail_mailer', 'value' => 'log'],
                ['key' => 'mail_password', 'value' => 'super-secret'],
            ],
        ])->assertOk()
            ->assertJsonPath('data.configuration.defaultRiskLevelCode', 'HIGH')
            ->assertJsonPath('data.configuration.defaultWorkflowSlaHours', 48)
            ->assertJsonPath('data.configuration.mailEnabled', true);

        $runtime = app(RuntimeConfiguration::class);
        $this->assertSame('LIB-2026-0007', $runtime->formatNumber(
            'document_number_format',
            7,
            ['YEAR' => 2026],
        ));
        $this->assertSame('super-secret', $runtime->secret('mail_password'));
        $this->assertNotSame(
            'super-secret',
            SystemConfiguration::query()->where('key', 'mail_password')->value('value'),
        );
        $this->getJson('/api/system-configurations')
            ->assertOk()
            ->assertJsonPath(
                'data.configurations',
                fn (array $items): bool => collect($items)
                    ->firstWhere('key', 'mail_password')['value'] === '',
            );

        $this->postJson('/api/system-configurations/test-email', [
            'recipient' => 'auditor@example.gov.ph',
        ])->assertOk();
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'system_configuration.test_email_sent',
        ]);

        $this->post('/api/system-configurations/logo', [
            'logo' => UploadedFile::fake()->createWithContent(
                'agis-logo.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
            ),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath(
                'data.configuration.logoUrl',
                fn ($url): bool => str_starts_with($url, '/storage/branding/'),
            );
        Storage::disk('public')->assertExists(
            str_replace('/storage/', '', $runtime->publicValues()['logoUrl']),
        );
    }

    public function test_detail_view_logging_is_permission_checked_and_deduplicated(): void
    {
        Sanctum::actingAs($this->user('admin'));
        $office = Office::query()->where('code', 'CIAS')->firstOrFail();
        $payload = [
            'module' => 'CORE',
            'recordType' => 'OFFICE',
            'recordId' => $office->id,
            'recordCode' => $office->code,
            'recordLabel' => $office->name,
            'route' => '/office-registry',
        ];

        $this->postJson('/api/record-views', $payload)
            ->assertOk()
            ->assertJsonPath('data.recorded', true);
        $this->postJson('/api/record-views', $payload)
            ->assertOk()
            ->assertJsonPath('data.recorded', false);

        $this->assertSame(
            1,
            ActivityLog::query()->where('action', 'core.office.viewed')->count(),
        );

        Sanctum::actingAs($this->user('auditee'));
        $this->postJson('/api/record-views', [
            ...$payload,
            'recordType' => 'ACCESS_ROLE',
        ])->assertForbidden();
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
