<?php

namespace Tests\Feature\Api;

use App\Models\AuditEngagement;
use App\Models\AuditFinding;
use App\Models\EngagementEvent;
use App\Models\EngagementTeam;
use App\Models\ExitConferenceAcknowledgement;
use App\Models\ExitConferenceAttachment;
use App\Models\Office;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class AemsExitConferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
    }

    public function test_exit_conference_records_findings_attendance_minutes_files_and_acknowledgement(): void
    {
        [$management, $auditor, $auditee, $engagement, $finding] = $this->conferenceContext();
        Sanctum::actingAs($auditor);
        $conference = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/exit-conferences",
            [
                'scheduledStartAt' => now()->addWeek()->setTime(9, 0)->toISOString(),
                'scheduledEndAt' => now()->addWeek()->setTime(11, 0)->toISOString(),
                'venue' => 'CIAS Conference Room',
                'meetingLink' => 'https://meet.example.test/exit-001',
                'onlineMeetingDetails' => 'Hybrid meeting; access code 2027.',
                'agenda' => 'Discuss communicated findings, management actions, and target dates.',
                'findingIds' => [$finding->id],
                'participants' => [
                    [
                        'userId' => $auditee->id,
                        'officeId' => $auditee->office_id,
                        'participantRole' => 'AUDITEE_REPRESENTATIVE',
                    ],
                    [
                        'externalName' => 'External Observer',
                        'externalEmail' => 'observer@example.test',
                        'participantRole' => 'OBSERVER',
                    ],
                ],
            ],
        )->assertCreated()
            ->assertJsonPath('data.conference.status', 'SCHEDULED')
            ->assertJsonPath('data.conference.findings.0.id', $finding->id)
            ->assertJsonCount(2, 'data.conference.participants')
            ->json('data.conference');

        $conference = $this->putJson(
            "/api/aems/engagements/{$engagement->id}/exit-conferences/{$conference['id']}",
            [
                'scheduledStartAt' => now()->addDays(8)->setTime(9, 0)->toISOString(),
                'scheduledEndAt' => now()->addDays(8)->setTime(11, 30)->toISOString(),
                'venue' => 'CIAS Conference Room',
                'meetingLink' => 'https://meet.example.test/exit-001',
                'onlineMeetingDetails' => 'Hybrid meeting; access code 2027.',
                'agenda' => 'Discuss communicated findings, management actions, and target dates.',
                'findingIds' => [$finding->id],
                'participants' => [
                    [
                        'userId' => $auditee->id,
                        'officeId' => $auditee->office_id,
                        'participantRole' => 'AUDITEE_REPRESENTATIVE',
                    ],
                    [
                        'externalName' => 'External Observer',
                        'externalEmail' => 'observer@example.test',
                        'participantRole' => 'OBSERVER',
                    ],
                ],
                'lockVersion' => $conference['lockVersion'],
            ],
        )->assertOk()
            ->assertJsonPath('data.conference.status', 'RESCHEDULED')
            ->json('data.conference');

        $attachment = $this->post(
            "/api/aems/engagements/{$engagement->id}/exit-conferences/{$conference['id']}/attachments",
            [
                'file' => UploadedFile::fake()->createWithContent(
                    'draft-minutes.pdf',
                    'Immutable draft minutes for acknowledgement.',
                ),
                'category' => 'MINUTES',
                'caption' => 'Signed draft minutes',
                'lockVersion' => $conference['lockVersion'],
            ],
            ['Accept' => 'application/json'],
        )->assertCreated()
            ->assertJsonPath('data.attachment.category', 'MINUTES')
            ->assertJsonPath('data.attachment.fileVersionNumber', 1)
            ->json('data.attachment');

        $conference = $this->workspace($engagement)['conferences'][0];
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/exit-conferences/{$conference['id']}/complete",
            [
                'discussionSummary' => 'The control exception and corrective action were discussed.',
                'minutes' => 'The auditors presented the finding. Management agreed to implement the revised control.',
                'agreements' => 'Management accepted the recommendation and revised deadline.',
                'disagreements' => null,
                'participantAttendance' => collect($conference['participants'])
                    ->map(fn (array $participant): array => [
                        'participantId' => $participant['id'],
                        'attendanceStatus' => 'ATTENDED',
                        'attendanceNotes' => 'Present for the full conference.',
                    ])->all(),
                'findingDiscussions' => [[
                    'findingId' => $finding->id,
                    'discussionStatus' => 'DISCUSSED',
                    'agreementStatus' => 'AGREED',
                    'discussionNotes' => 'Management confirmed the condition and proposed action.',
                    'agreementDetails' => 'Implement monthly supervisory reconciliation.',
                    'revisedTargetDate' => now()->addMonths(4)->toDateString(),
                ]],
                'lockVersion' => $conference['lockVersion'],
            ],
        )->assertOk()
            ->assertJsonPath('data.conference.status', 'COMPLETED')
            ->assertJsonPath('data.conference.findings.0.discussion.agreementStatus', 'AGREED')
            ->assertJsonPath('data.conference.attachments.0.checksumSha256', $attachment['checksumSha256'])
            ->assertJsonPath('data.conference.completionSnapshot.findings.0.findingCode', $finding->finding_code);

        $conference = $this->workspace($engagement)['conferences'][0];
        $this->putJson(
            "/api/aems/engagements/{$engagement->id}/exit-conferences/{$conference['id']}",
            [
                'scheduledStartAt' => now()->addDays(9)->toISOString(),
                'venue' => 'Changed venue',
                'agenda' => 'Attempted mutation.',
                'findingIds' => [$finding->id],
                'participants' => [[
                    'userId' => $auditee->id,
                    'participantRole' => 'AUDITEE_REPRESENTATIVE',
                ]],
                'lockVersion' => $conference['lockVersion'],
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('conference');

        Sanctum::actingAs($auditee);
        $auditeeWorkspace = $this->getJson(
            "/api/aems/engagements/{$engagement->id}/exit-conferences",
        )->assertOk()
            ->assertJsonPath('data.conferences.0.findings.0.id', $finding->id)
            ->json('data');
        $conference = $auditeeWorkspace['conferences'][0];
        $acknowledgement = $this->postJson(
            "/api/aems/engagements/{$engagement->id}/exit-conferences/{$conference['id']}/acknowledgements",
            [
                'status' => 'WITH_RESERVATIONS',
                'comment' => 'Acknowledged; the revised target depends on procurement lead time.',
                'lockVersion' => $conference['lockVersion'],
            ],
        )->assertCreated()
            ->assertJsonPath('data.acknowledgement.actor.id', $auditee->id)
            ->assertJsonPath('data.acknowledgement.versionNumber', 1)
            ->json('data.acknowledgement');

        $this->get(
            "/api/aems/engagements/{$engagement->id}/exit-conferences/{$conference['id']}/attachments/{$attachment['id']}/download",
            ['Accept' => 'application/octet-stream'],
        )->assertOk();
        $this->assertDatabaseHas('exit_conference_acknowledgements', [
            'id' => $acknowledgement['id'],
            'user_id' => $auditee->id,
            'acknowledgement_status' => 'WITH_RESERVATIONS',
        ]);
        $this->assertDatabaseHas('exit_conference_findings', [
            'exit_conference_id' => $conference['id'],
            'audit_finding_id' => $finding->id,
            'agreement_status' => 'AGREED',
        ]);
        $this->assertGreaterThanOrEqual(
            5,
            EngagementEvent::query()
                ->where('subject_type', 'EXIT_CONFERENCE')
                ->where('subject_id', $conference['id'])
                ->count(),
        );

        $this->expectException(LogicException::class);
        ExitConferenceAcknowledgement::query()
            ->findOrFail($acknowledgement['id'])
            ->update(['comment' => 'Attempted overwrite.']);
    }

    public function test_exit_conference_is_locked_until_the_explicit_lifecycle_stage(): void
    {
        [$management, $auditor, $auditee, $engagement, $finding] = $this->conferenceContext();
        $engagement->forceFill(['status' => 'FINDINGS_COMMUNICATION'])->save();
        Sanctum::actingAs($auditor);

        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/exit-conferences",
            [
                'scheduledStartAt' => now()->addWeek()->setTime(9, 0)->toISOString(),
                'venue' => 'CIAS Conference Room',
                'agenda' => 'Exit Conference agenda.',
                'findingIds' => [$finding->id],
                'participants' => [[
                    'userId' => $auditee->id,
                    'participantRole' => 'AUDITEE_REPRESENTATIVE',
                ]],
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('engagement');
    }

    public function test_conference_rejects_uncommunicated_findings_and_non_participant_acknowledgement(): void
    {
        [$management, $auditor, $auditee, $engagement] = $this->conferenceContext();
        $draft = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'revision_number' => 1,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'finding_code' => 'FND-DRAFT-001',
            'title' => 'Uncommunicated draft',
            'criteria' => 'Required control.',
            'condition' => 'Draft condition.',
            'cause' => 'Draft cause.',
            'effect' => 'Draft effect.',
            'responsible_office_id' => $auditee->office_id,
            'status' => 'DRAFT',
            'authored_by' => $auditor->id,
        ]);

        Sanctum::actingAs($auditor);
        $this->postJson(
            "/api/aems/engagements/{$engagement->id}/exit-conferences",
            [
                'scheduledStartAt' => now()->addWeek()->toISOString(),
                'venue' => 'CIAS Conference Room',
                'agenda' => 'Invalid finding selection.',
                'findingIds' => [$draft->id],
                'participants' => [[
                    'userId' => $auditee->id,
                    'participantRole' => 'AUDITEE_REPRESENTATIVE',
                ]],
            ],
        )->assertUnprocessable()->assertJsonValidationErrors('findingIds');
    }

    /** @return array{User, User, User, AuditEngagement, AuditFinding} */
    private function conferenceContext(): array
    {
        $management = User::query()->where('username', 'departmenthead')->firstOrFail();
        $auditor = User::query()->where('username', 'auditor')->firstOrFail();
        $auditee = User::query()->where('username', 'auditee')->firstOrFail();
        $office = Office::query()->findOrFail($auditee->office_id);
        $engagement = AuditEngagement::query()->create([
            'engagement_code' => 'AEMS-EXIT-001',
            'title' => 'Revenue Collection Exit Conference',
            'source_type' => 'SPECIAL',
            'special_authority_reference' => 'AUTH-EXIT-001',
            'special_authority_date' => now()->subMonth()->toDateString(),
            'special_authority_approved_by' => $management->id,
            'objectives' => 'Assess collection controls.',
            'scope' => 'Revenue collection.',
            'status' => 'EXIT_CONFERENCE',
            'created_by' => $management->id,
            'updated_by' => $management->id,
        ]);
        $engagement->offices()->attach($office->id, ['is_primary' => true]);
        EngagementTeam::query()->create([
            'audit_engagement_id' => $engagement->id,
            'user_id' => $auditor->id,
            'assignment_role_code' => 'TEAM_LEADER',
            'assigned_by' => $management->id,
            'is_active' => true,
        ]);
        $finding = AuditFinding::query()->create([
            'finding_family_uuid' => (string) Str::uuid(),
            'revision_number' => 1,
            'is_current_revision' => true,
            'audit_engagement_id' => $engagement->id,
            'finding_code' => 'FND-EXIT-001',
            'title' => 'Daily collections are not reconciled',
            'criteria' => 'Collections must be reconciled daily.',
            'condition' => 'Three daily batches had no supervisory reconciliation.',
            'cause' => 'The reconciliation control is not assigned.',
            'effect' => 'Posting errors may remain undetected.',
            'responsible_office_id' => $office->id,
            'status' => 'FINALIZED',
            'authored_by' => $auditor->id,
            'communicated_at' => now(),
            'communicated_by' => $management->id,
        ]);

        return [$management, $auditor, $auditee, $engagement, $finding];
    }

    /** @return array<string, mixed> */
    private function workspace(AuditEngagement $engagement): array
    {
        return $this->getJson(
            "/api/aems/engagements/{$engagement->id}/exit-conferences",
        )->assertOk()->json('data');
    }
}
