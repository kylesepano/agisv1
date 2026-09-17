<?php

namespace Tests\Feature;

use App\Integrations\Aems\ArmisResourcePlanningGateway;
use App\Models\ArmisActualPersonDay;
use App\Models\ArmisAssignmentCompetency;
use App\Models\ArmisAvailabilityPeriod;
use App\Models\ArmisCapacitySubmission;
use App\Models\ArmisCompetency;
use App\Models\ArmisEngagementAssignment;
use App\Models\ArmisRequirementCompetency;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisResourceRequirement;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Models\MasterListItem;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\AemsIntegrationStatusService;
use App\Services\RuntimeConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArmisProviderAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_armis_resource_cutover_defaults_to_armis_authority(): void
    {
        $administrator = $this->user('admin');
        $status = app(AemsIntegrationStatusService::class)->status($administrator)['armis'];

        $this->assertSame('ARMIS_AUTHORITATIVE', $status['mode']);
        $this->assertSame(ArmisResourcePlanningGateway::class, $status['activeProvider']);
        $this->assertNull($status['shadowProvider']);
        $this->assertTrue($status['authoritative']);
        $this->assertFalse($status['authoritySwitchAllowed']);
        $this->assertSame(
            ['ARMIS_AUTHORITATIVE'],
            $status['supportedModes'],
        );
        $this->assertSame('ARMIS_AUTHORITATIVE', app(RuntimeConfiguration::class)->armisProviderMode());
        $this->assertDatabaseHas('system_configurations', ['key' => 'armis_provider_mode']);
    }

    public function test_historical_provider_modes_are_rejected_and_armis_remains_active(): void
    {
        $administrator = $this->user('admin');
        Sanctum::actingAs($administrator);

        $this->putJson('/api/system-configurations', [
            'configurations' => [
                ['key' => 'armis_provider_mode', 'value' => 'ARMIS_SHADOW'],
            ],
        ])->assertUnprocessable();

        $status = app(AemsIntegrationStatusService::class)->status($administrator)['armis'];
        $this->assertSame('ARMIS_AUTHORITATIVE', $status['mode']);
        $this->assertSame(ArmisResourcePlanningGateway::class, $status['activeProvider']);
        $this->assertNull($status['shadowProvider']);
        $this->assertSame('ARMIS_ADAPTER', $status['armisAdapter']['mode']);
        $this->assertTrue($status['authoritative']);
        $this->assertFalse($status['authoritySwitchAllowed']);
    }

    public function test_authoritative_mode_is_the_only_writable_provider_setting(): void
    {
        Sanctum::actingAs($this->user('admin'));

        $this->putJson('/api/system-configurations', [
            'configurations' => [
                ['key' => 'armis_provider_mode', 'value' => 'ARMIS_AUTHORITATIVE'],
            ],
        ])->assertOk();

        $this->assertSame('ARMIS_AUTHORITATIVE', app(RuntimeConfiguration::class)->armisProviderMode());
    }

    public function test_armis_adapter_reads_approved_current_ledgers_in_the_gateway_shape(): void
    {
        [$admin, $reviewer, $profile, $engagement, $competency] = $this->context();
        $adapter = app(ArmisResourcePlanningGateway::class);

        $this->assertSame(120.0, $adapter->capacityFor(2026, $profile->user_id));
        $this->assertSame([
            [
                'id' => $competency->id,
                'code' => 'FINANCIAL_AUDIT',
                'label' => $competency->label,
                'proficiencyLevel' => 'ADVANCED',
            ],
        ], $adapter->skills([$profile->user_id], [$competency->id])[$profile->user_id]);
        $this->assertSame('Leave', $adapter->unavailability($profile->user_id, now()->startOfMonth(), now()->endOfMonth())[0]['typeLabel']);
        $this->assertSame($competency->id, $adapter->requirements($engagement)[0]['specializationId']);
        $this->assertSame(14.5, $adapter->engagementActualPersonDays($engagement));
        $this->assertSame(14.5, $adapter->assignmentActualPersonDays($engagement->teamMembers()->firstOrFail()));
        $this->assertSame('ARMIS_ADAPTER', $adapter->status()['mode']);

        $this->assertNotNull($admin->id);
        $this->assertNotNull($reviewer->id);
    }

    /** @return array{User, User, ArmisResourceProfile, AuditEngagement, MasterListItem} */
    private function context(): array
    {
        $admin = $this->user('agisadmin');
        $reviewer = $this->user('departmenthead');
        $resourceUser = $this->user('auditor');
        $competency = MasterListItem::query()
            ->where('code', 'FINANCIAL_AUDIT')
            ->whereHas('masterList', fn ($query) => $query->where('code', 'IAP_AUDITOR_SPECIALIZATION'))
            ->firstOrFail();
        $profile = ArmisResourceProfile::query()->create([
            'resource_code' => 'ARMIS-6A-'.str()->upper(str()->random(6)),
            'user_id' => $resourceUser->id,
            'office_id' => $resourceUser->office_id,
            'category' => 'AUDIT_RESOURCE',
            'status' => 'ACTIVE',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        ArmisCapacitySubmission::query()->create([
            'resource_profile_id' => $profile->id,
            'fiscal_year' => 2026,
            'version_number' => 1,
            'available_person_days' => 120,
            'status' => 'APPROVED',
            'is_current_revision' => true,
            'created_by' => $admin->id,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);
        ArmisAvailabilityPeriod::query()->create([
            'availability_family_uuid' => (string) Str::uuid(),
            'resource_profile_id' => $profile->id,
            'version_number' => 1,
            'is_current_revision' => true,
            'availability_type' => 'LEAVE',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'person_days' => 5,
            'status' => 'APPROVED',
            'created_by' => $admin->id,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);
        ArmisCompetency::query()->create([
            'competency_family_uuid' => (string) Str::uuid(),
            'resource_profile_id' => $profile->id,
            'competency_id' => $competency->id,
            'version_number' => 1,
            'is_current_revision' => true,
            'proficiency_level' => 'ADVANCED',
            'status' => 'VERIFIED',
            'verified_by' => $reviewer->id,
            'verified_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'ARMIS-6A-ENG-'.str()->upper(str()->random(6)),
            'title' => 'ARMIS provider adapter test',
            'source_type' => 'SPECIAL',
            'special_authority_reference' => 'ARMIS-6A-TEST',
            'special_authority_date' => today(),
            'special_authority_approved_by' => $reviewer->id,
            'objectives' => 'Test the ARMIS provider adapter.',
            'scope' => 'ARMIS provider records.',
            'planned_start_date' => '2026-08-01',
            'planned_end_date' => '2026-08-31',
            'planned_person_days' => 20,
            'status' => 'FIELDWORK',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $engagement->offices()->attach($profile->office_id, ['is_primary' => true]);
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $profile->user_id,
            'assignment_role_code' => 'AUDITOR',
            'assigned_by' => $admin->id,
            'is_active' => true,
        ]);
        $requirement = ArmisResourceRequirement::query()->create([
            'source_module' => 'AEMS',
            'source_type' => 'AEMS_ASSIGNMENT',
            'source_id' => $engagement->id,
            'office_id' => $profile->office_id,
            'fiscal_year' => 2026,
            'title' => 'Financial audit competency',
            'required_person_days' => 20,
            'status' => 'APPROVED',
            'created_by' => $admin->id,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);
        ArmisRequirementCompetency::query()->create([
            'requirement_id' => $requirement->id,
            'competency_id' => $competency->id,
            'minimum_resources' => 1,
            'minimum_proficiency' => 'INTERMEDIATE',
        ]);
        $assignment = ArmisEngagementAssignment::query()->create([
            'assignment_family_uuid' => (string) Str::uuid(),
            'audit_engagement_id' => $engagement->id,
            'resource_profile_id' => $profile->id,
            'requirement_id' => $requirement->id,
            'version_number' => 1,
            'is_current_revision' => true,
            'assignment_role_code' => 'AUDITOR',
            'assigned_from' => '2026-08-01',
            'assigned_until' => '2026-08-31',
            'planned_person_days' => 20,
            'status' => 'LOCKED',
            'created_by' => $admin->id,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);
        ArmisAssignmentCompetency::query()->create([
            'assignment_id' => $assignment->id,
            'competency_id' => $competency->id,
            'minimum_proficiency' => 'INTERMEDIATE',
        ]);
        ArmisActualPersonDay::query()->create([
            'actual_family_uuid' => (string) Str::uuid(),
            'resource_profile_id' => $profile->id,
            'assignment_id' => $assignment->id,
            'source_module' => 'AEMS',
            'source_type' => 'AEMS_ASSIGNMENT',
            'source_id' => $engagement->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'version_number' => 1,
            'actual_person_days' => 14.5,
            'status' => 'APPROVED',
            'is_current_revision' => true,
            'created_by' => $admin->id,
            'approved_by' => $reviewer->id,
            'approved_at' => now(),
        ]);

        return [$admin, $reviewer, $profile, $engagement, $competency];
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
