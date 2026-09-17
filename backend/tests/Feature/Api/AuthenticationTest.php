<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const SPA_ORIGIN = 'http://localhost:5173';

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        $this->withHeader('Origin', self::SPA_ORIGIN);
    }

    public function test_demo_accounts_are_available_when_the_local_demo_is_enabled(): void
    {
        config(['demo.enabled' => true]);

        $this->getJson('/api/demo-accounts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(10, 'data')
            ->assertJsonFragment([
                'employeeId' => 'BPLD-HEAD',
                'name' => 'Oxanna S. Custodio',
                'office' => 'Business Permits and Licensing Division',
            ])
            ->assertJsonFragment([
                'employeeId' => 'CIAS-HEAD-001',
                'roleCode' => 'cias_management',
            ])
            ->assertJsonFragment([
                'employeeId' => 'CIAS-AUD-001',
                'name' => 'Charry Bagongon',
            ])
            ->assertJsonFragment([
                'employeeId' => 'CIAS-AUD-002',
                'name' => 'Kristine Yare',
            ])
            ->assertJsonFragment([
                'employeeId' => 'CIAS-AUD-003',
                'name' => 'Michele Dampog',
            ]);
    }

    public function test_demo_accounts_are_hidden_when_the_local_demo_is_disabled(): void
    {
        config(['demo.enabled' => false]);

        $this->getJson('/api/demo-accounts')->assertNotFound();
    }

    public function test_oxanna_demo_credentials_sign_in_to_the_existing_bpld_account(): void
    {
        $account = collect($this->getJson('/api/demo-accounts')->assertOk()->json('data'))
            ->firstWhere('employeeId', 'BPLD-HEAD');

        $this->assertNotNull($account);
        $this->assertSame(1, User::query()->where('username', 'bpld.head')->count());
        $this->postJson('/api/login', [
            'employeeId' => $account['employeeId'],
            'password' => $account['password'],
        ])->assertOk()
            ->assertJsonPath('data.user.name', 'Oxanna S. Custodio')
            ->assertJsonPath('data.user.office', 'Business Permits and Licensing Division')
            ->assertJsonPath('data.user.roleCode', 'auditee_representative');
    }

    public function test_user_can_sign_in_restore_the_session_and_sign_out(): void
    {
        // CIAS-AUD-001 is the reference-roster Charry Bagongon account;
        // `auditor` remains the lead-auditor compatibility username.
        $user = User::query()->where('employee_id', 'CIAS-AUD-001')->firstOrFail();

        $this->postJson('/api/login', [
            'employeeId' => '  cias-aud-001  ',
            'password' => 'lala',
            'remember' => true,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.employeeId', 'CIAS-AUD-001')
            ->assertJsonPath('data.user.roleCode', 'agis_user')
            ->assertJsonPath('data.user.office', 'City Internal Audit Office')
            ->assertJsonPath('data.user.permissions.0', fn ($permission) => is_string($permission));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'auth.login_succeeded',
        ]);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.employeeId', 'CIAS-AUD-001');

        $this->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Signed out successfully.');

        // Feature tests reuse Sanctum's request guard; production requests do not.
        Auth::forgetGuards();

        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Your session has expired. Please sign in again.');
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'auth.logout',
        ]);
    }

    public function test_invalid_password_is_rejected_and_audited(): void
    {
        $user = User::query()->where('username', 'admin')->firstOrFail();

        $this->postJson('/api/login', [
            'employeeId' => $user->employee_id,
            'password' => 'incorrect-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.employeeId.0', 'The Employee ID or password is incorrect.');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'failed_login_attempts' => 1,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_user_id' => $user->id,
            'action' => 'auth.login_failed',
        ]);
    }

    public function test_five_invalid_passwords_temporarily_lock_the_account(): void
    {
        $user = User::query()->where('username', 'admin')->firstOrFail();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/login', [
                'employeeId' => $user->employee_id,
                'password' => 'incorrect-password',
            ])->assertUnprocessable();
        }

        $user->refresh();

        $this->assertSame(5, $user->failed_login_attempts);
        $this->assertTrue($user->isLocked());
        $this->assertSame(
            5,
            ActivityLog::query()
                ->where('subject_user_id', $user->id)
                ->where('action', 'auth.login_failed')
                ->count(),
        );
    }

    public function test_locked_and_inactive_accounts_cannot_sign_in(): void
    {
        $lockedUser = User::query()->where('username', 'admin')->firstOrFail();
        $lockedUser->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->postJson('/api/login', [
            'employeeId' => $lockedUser->employee_id,
            'password' => config('demo.accounts.0.password'),
        ])
            ->assertStatus(423)
            ->assertJsonPath('message', 'This account is temporarily locked. Please try again later.');

        $inactiveUser = User::query()->where('username', 'auditor')->firstOrFail();
        $inactiveUser->forceFill(['is_active' => false])->save();

        $this->postJson('/api/login', [
            'employeeId' => $inactiveUser->employee_id,
            'password' => 'lala',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.employeeId.0', 'The Employee ID or password is incorrect.');
    }

    public function test_an_expired_lock_starts_a_fresh_failed_attempt_window(): void
    {
        $user = User::query()->where('username', 'admin')->firstOrFail();
        $user->forceFill([
            'failed_login_attempts' => 5,
            'locked_until' => now()->subMinute(),
        ])->save();

        $this->postJson('/api/login', [
            'employeeId' => $user->employee_id,
            'password' => 'incorrect-password',
        ])->assertUnprocessable();

        $user->refresh();

        $this->assertSame(1, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }

    public function test_protected_profile_requires_an_authenticated_session(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Your session has expired. Please sign in again.');
    }

    public function test_login_throttle_has_a_broader_per_ip_limit(): void
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson('/api/login', [
                'employeeId' => "ROTATING-{$attempt}",
                'password' => '',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'employeeId' => 'ROTATING-31',
            'password' => '',
        ])
            ->assertTooManyRequests()
            ->assertJsonPath(
                'message',
                'Too many sign-in attempts. Please wait one minute and try again.',
            );
    }

    public function test_health_check_confirms_the_active_database_driver(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonPath('data.database', 'sqlite');
    }
}
