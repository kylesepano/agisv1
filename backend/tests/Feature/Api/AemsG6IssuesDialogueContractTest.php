<?php

namespace Tests\Feature\Api;

use App\Models\AemsDialogueDueProcess;
use App\Models\AuditIssue;
use App\Models\ManagementResponse;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AemsG6IssuesDialogueContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_g6_status_and_dialogue_contracts_are_registered(): void
    {
        $this->assertContains('WITHDRAWN', AuditIssue::STATUSES);
        $this->assertContains('WITHDRAWN', AuditIssue::DISPOSITIONS);
        $this->assertTrue(AuditIssue::STATUS_COMPATIBILITY['WITHDRAWN']['terminal']);
        $this->assertContains('EXTENSION_REQUESTED', ManagementResponse::STATUSES);
        $this->assertContains('EXTENSION_APPROVED', ManagementResponse::STATUSES);
        $this->assertContains('SUPPLEMENTAL', ManagementResponse::RESPONSE_KINDS);
        $this->assertContains('EXTENSION_REQUESTED', AemsDialogueDueProcess::TYPES);
        $this->assertContains('LATE_RESPONSE', AemsDialogueDueProcess::TYPES);
        $this->assertContains('SUPPLEMENTAL_RESPONSE', AemsDialogueDueProcess::TYPES);
        $this->assertDatabaseHas('permissions', ['code' => 'aems.issue.withdraw']);
        $this->assertDatabaseHas('permissions', ['code' => 'aems.afr.transmit']);
        $this->assertDatabaseHas('permissions', ['code' => 'aems.management-response.request_extension']);
    }

    public function test_g6_transmittal_tables_are_migrated_with_append_only_events(): void
    {
        $this->assertDatabaseHas('migrations', ['migration' => '2026_09_02_000000_add_aems_g6_issue_dialogue_contract']);
        $this->assertTrue(\Schema::hasColumns('aems_finding_transmittals', [
            'transmittal_code', 'finding_revision_number', 'content_snapshot', 'lock_version',
        ]));
        $this->assertTrue(\Schema::hasColumns('aems_finding_transmittal_recipients', [
            'delivery_status', 'acknowledged_at', 'acknowledged_by', 'lock_version',
        ]));
        $this->assertTrue(\Schema::hasColumns('management_responses', [
            'response_kind', 'extension_requested_due_date', 'extension_approved_due_date', 'submitted_late',
        ]));
    }
}
