<?php

use App\Support\CmsIntakeReferentialPreflight;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preserves the immutable AEMS transfer envelope and initializes the separate
 * CMS operational case and append-only event history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_recommendations', function (Blueprint $table): void {
            $table->foreignId('audit_engagement_id')
                ->nullable()
                ->after('transfer_key')
                ->constrained('audit_engagements')
                ->restrictOnDelete();
            $table->foreignId('audit_report_id')
                ->nullable()
                ->after('audit_engagement_id')
                ->constrained('audit_reports')
                ->restrictOnDelete();
            $table->string('report_code_snapshot', 100)
                ->nullable()
                ->after('audit_report_version_id');
            $table->unsignedInteger('report_version_number_snapshot')
                ->nullable()
                ->after('report_code_snapshot');
            $table->timestamp('report_issued_at')
                ->nullable()
                ->after('report_version_number_snapshot');
            $table->foreignId('report_issued_by')
                ->nullable()
                ->after('report_issued_at')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('report_checksum_sha256', 64)
                ->nullable()
                ->after('report_issued_by');
            $table->foreignId('confidentiality_level_id')
                ->nullable()
                ->after('report_checksum_sha256')
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->string('confidentiality_code_snapshot', 80)
                ->nullable()
                ->after('confidentiality_level_id');
            $table->string('confidentiality_label_snapshot')
                ->nullable()
                ->after('confidentiality_code_snapshot');
            $table->foreignId('risk_rating_id')
                ->nullable()
                ->after('audit_finding_id')
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->string('risk_code_snapshot', 80)
                ->nullable()
                ->after('risk_rating_id');
            $table->string('risk_label_snapshot')
                ->nullable()
                ->after('risk_code_snapshot');
            $table->jsonb('responsible_office_snapshot')
                ->nullable()
                ->after('responsible_office_id');
            $table->foreignId('lead_responsible_office_id')
                ->nullable()
                ->after('responsible_office_snapshot')
                ->constrained('offices')
                ->restrictOnDelete();
            $table->date('original_target_implementation_date')
                ->nullable()
                ->after('target_implementation_date');
            $table->unsignedInteger('source_schema_version')
                ->default(1)
                ->after('original_target_implementation_date');

            $table->index(
                ['audit_engagement_id', 'audit_report_id'],
                'cms_intake_source_report_idx',
            );
            $table->index(
                ['lead_responsible_office_id', 'status'],
                'cms_intake_office_status_idx',
            );
        });

        Schema::create('cms_recommendation_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_id')
                ->unique()
                ->constrained('cms_recommendations')
                ->restrictOnDelete();
            $table->string('status_code', 40)->default('TRANSFERRED')->index();
            $table->date('effective_target_implementation_date')->nullable()->index();
            $table->foreignId('lead_responsible_office_id')
                ->nullable()
                ->constrained('offices')
                ->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->index(
                ['lead_responsible_office_id', 'status_code'],
                'cms_case_office_status_idx',
            );
        });

        Schema::create('cms_recommendation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')
                ->constrained('cms_recommendation_cases')
                ->restrictOnDelete();
            $table->foreignId('cms_recommendation_id')
                ->constrained('cms_recommendations')
                ->restrictOnDelete();
            $table->string('idempotency_key', 160)->unique();
            $table->string('event_code', 80)->index();
            $table->string('source_module', 20)->default('AEMS');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('previous_status', 40)->nullable();
            $table->string('new_status', 40);
            $table->jsonb('event_metadata');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->index(
                ['cms_recommendation_case_id', 'created_at'],
                'cms_case_event_history_idx',
            );
            $table->index(
                ['cms_recommendation_id', 'created_at'],
                'cms_intake_event_history_idx',
            );
        });

        $this->backfillExistingIntakes();
        $this->assertValidStatuses();
        Schema::table('cms_recommendations', function (Blueprint $table): void {
            $table->string('status', 30)->default('TRANSFERRED')->change();
        });
        CmsIntakeReferentialPreflight::assertNoOrphanedRecommendationPointers();

        Schema::table('audit_recommendations', function (Blueprint $table): void {
            $table->foreign('cms_recommendation_id')
                ->references('id')
                ->on('cms_recommendations')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE cms_recommendations
                 ADD CONSTRAINT cms_intake_status_check
                 CHECK (status = 'TRANSFERRED')",
            );
            DB::statement(
                "ALTER TABLE cms_recommendation_cases
                 ADD CONSTRAINT cms_case_status_check
                 CHECK (status_code = 'TRANSFERRED')",
            );
        }
    }

    public function down(): void
    {
        Schema::table('audit_recommendations', function (Blueprint $table): void {
            $table->dropForeign(['cms_recommendation_id']);
        });

        Schema::dropIfExists('cms_recommendation_events');
        Schema::dropIfExists('cms_recommendation_cases');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE cms_recommendations DROP CONSTRAINT IF EXISTS cms_intake_status_check',
            );
        }
        Schema::table('cms_recommendations', function (Blueprint $table): void {
            $table->string('status', 30)->default('OPEN')->change();
        });

        Schema::table('cms_recommendations', function (Blueprint $table): void {
            $table->dropIndex('cms_intake_source_report_idx');
            $table->dropIndex('cms_intake_office_status_idx');
            $table->dropConstrainedForeignId('audit_engagement_id');
            $table->dropConstrainedForeignId('audit_report_id');
            $table->dropConstrainedForeignId('report_issued_by');
            $table->dropConstrainedForeignId('confidentiality_level_id');
            $table->dropConstrainedForeignId('risk_rating_id');
            $table->dropConstrainedForeignId('lead_responsible_office_id');
            $table->dropColumn([
                'report_code_snapshot',
                'report_version_number_snapshot',
                'report_issued_at',
                'report_checksum_sha256',
                'confidentiality_code_snapshot',
                'confidentiality_label_snapshot',
                'risk_code_snapshot',
                'risk_label_snapshot',
                'responsible_office_snapshot',
                'original_target_implementation_date',
                'source_schema_version',
            ]);
        });
    }

    private function backfillExistingIntakes(): void
    {
        DB::table('cms_recommendations')
            ->orderBy('id')
            ->each(function (object $intake): void {
                $recommendation = DB::table('audit_recommendations')
                    ->where('id', $intake->source_audit_recommendation_id)
                    ->first();
                $finding = DB::table('audit_findings')
                    ->where('id', $intake->audit_finding_id)
                    ->first();
                $version = DB::table('audit_report_versions')
                    ->where('id', $intake->audit_report_version_id)
                    ->first();
                $report = $version
                    ? DB::table('audit_reports')->where('id', $version->audit_report_id)->first()
                    : null;
                $engagement = $finding
                    ? DB::table('audit_engagements')->where('id', $finding->audit_engagement_id)->first()
                    : null;
                $office = $recommendation
                    ? DB::table('offices')->where('id', $recommendation->responsible_office_id)->first()
                    : null;
                $risk = $finding?->risk_rating_id
                    ? DB::table('master_list_items')->where('id', $finding->risk_rating_id)->first()
                    : null;
                $confidentiality = $report?->confidentiality_level_id
                    ? DB::table('master_list_items')->where('id', $report->confidentiality_level_id)->first()
                    : null;
                $actor = DB::table('users')->where('id', $intake->transferred_by)->first();
                $responsibleOffices = $office ? [[
                    'id' => $office->id,
                    'code' => $office->code,
                    'name' => $office->name,
                    'acronym' => $office->acronym,
                    'isLead' => true,
                ]] : [];
                $engagementOffices = $engagement
                    ? DB::table('audit_engagement_offices as link')
                        ->join('offices', 'offices.id', '=', 'link.office_id')
                        ->where('link.audit_engagement_id', $engagement->id)
                        ->orderByDesc('link.is_primary')
                        ->orderBy('offices.id')
                        ->get([
                            'offices.id',
                            'offices.code',
                            'offices.name',
                            'offices.acronym',
                            'link.is_primary',
                        ])
                        ->map(fn (object $item): array => [
                            'id' => $item->id,
                            'code' => $item->code,
                            'name' => $item->name,
                            'acronym' => $item->acronym,
                            'isPrimary' => (bool) $item->is_primary,
                        ])->all()
                    : [];

                $snapshot = [
                    'sourceSchemaVersion' => 1,
                    'engagement' => [
                        'id' => $engagement?->id,
                        'code' => $engagement?->engagement_code,
                        'title' => $engagement?->title,
                        'offices' => $engagementOffices,
                    ],
                    'finding' => [
                        'id' => $finding?->id,
                        'code' => $finding?->finding_code,
                        'title' => $finding?->title,
                        'risk' => [
                            'id' => $risk?->id,
                            'code' => $risk?->code,
                            'label' => $risk?->label,
                        ],
                    ],
                    'recommendation' => [
                        'id' => $recommendation?->id,
                        'code' => $recommendation?->recommendation_code
                            ?? $intake->recommendation_code,
                        'wording' => $recommendation?->recommendation,
                        'responsibleOffices' => $responsibleOffices,
                        'leadResponsibleOfficeId' => $recommendation?->responsible_office_id
                            ?? $intake->responsible_office_id,
                        'originalTargetImplementationDate' => $recommendation?->target_implementation_date
                            ?? $intake->target_implementation_date,
                    ],
                    'report' => [
                        'id' => $report?->id,
                        'code' => $report?->report_code,
                        'versionId' => $version?->id,
                        'versionNumber' => $version?->version_number,
                        'issuedAt' => $report?->issued_at,
                        'issuedBy' => $report?->issued_by,
                        'checksumSha256' => $version?->checksum_sha256,
                        'confidentiality' => [
                            'id' => $confidentiality?->id,
                            'code' => $confidentiality?->code,
                            'label' => $confidentiality?->label,
                        ],
                    ],
                    'transfer' => [
                        'key' => $intake->transfer_key,
                        'actor' => [
                            'id' => $actor?->id ?? $intake->transferred_by,
                            'name' => $actor?->name,
                        ],
                        'transferredAt' => $intake->transferred_at,
                    ],
                ];

                DB::table('cms_recommendations')
                    ->where('id', $intake->id)
                    ->update([
                        'audit_engagement_id' => $engagement?->id,
                        'audit_report_id' => $report?->id,
                        'report_code_snapshot' => $report?->report_code,
                        'report_version_number_snapshot' => $version?->version_number,
                        'report_issued_at' => $report?->issued_at,
                        'report_issued_by' => $report?->issued_by,
                        'report_checksum_sha256' => $version?->checksum_sha256,
                        'confidentiality_level_id' => $confidentiality?->id,
                        'confidentiality_code_snapshot' => $confidentiality?->code,
                        'confidentiality_label_snapshot' => $confidentiality?->label,
                        'risk_rating_id' => $risk?->id,
                        'risk_code_snapshot' => $risk?->code,
                        'risk_label_snapshot' => $risk?->label,
                        'responsible_office_snapshot' => json_encode(
                            $responsibleOffices,
                            JSON_THROW_ON_ERROR,
                        ),
                        'lead_responsible_office_id' => $recommendation?->responsible_office_id
                            ?? $intake->responsible_office_id,
                        'original_target_implementation_date' => $recommendation?->target_implementation_date
                            ?? $intake->target_implementation_date,
                        'source_schema_version' => 1,
                        'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                        'status' => $intake->status === 'OPEN'
                            ? 'TRANSFERRED'
                            : $intake->status,
                        'updated_at' => now(),
                    ]);

                $caseId = DB::table('cms_recommendation_cases')
                    ->where('cms_recommendation_id', $intake->id)
                    ->value('id');
                if (! $caseId) {
                    $caseId = DB::table('cms_recommendation_cases')->insertGetId([
                        'cms_recommendation_id' => $intake->id,
                        'status_code' => 'TRANSFERRED',
                        'effective_target_implementation_date' => $recommendation?->target_implementation_date
                            ?? $intake->target_implementation_date,
                        'lead_responsible_office_id' => $recommendation?->responsible_office_id
                            ?? $intake->responsible_office_id,
                        'opened_at' => $intake->transferred_at,
                        'created_by' => $intake->transferred_by,
                        'lock_version' => 1,
                        'created_at' => $intake->transferred_at,
                        'updated_at' => $intake->transferred_at,
                    ]);
                }

                DB::table('cms_recommendation_events')->insertOrIgnore([
                    'cms_recommendation_case_id' => $caseId,
                    'cms_recommendation_id' => $intake->id,
                    'idempotency_key' => "cms-intake:{$intake->id}",
                    'event_code' => 'INTAKE_CREATED',
                    'source_module' => 'AEMS',
                    'actor_id' => $intake->transferred_by,
                    'previous_status' => null,
                    'new_status' => 'TRANSFERRED',
                    'event_metadata' => json_encode([
                        'backfilled' => true,
                        'engagementId' => $engagement?->id,
                        'reportId' => $report?->id,
                        'reportVersionId' => $version?->id,
                        'findingId' => $finding?->id,
                        'recommendationId' => $recommendation?->id,
                        'transferKey' => $intake->transfer_key,
                        'leadResponsibleOfficeId' => $recommendation?->responsible_office_id
                            ?? $intake->responsible_office_id,
                        'originalTargetImplementationDate' => $recommendation?->target_implementation_date
                            ?? $intake->target_implementation_date,
                        'resultingCaseStatus' => 'TRANSFERRED',
                    ], JSON_THROW_ON_ERROR),
                    'ip_address' => null,
                    'user_agent' => null,
                    'created_at' => $intake->transferred_at,
                ]);
            });
    }

    private function assertValidStatuses(): void
    {
        $invalid = DB::table('cms_recommendations')
            ->where('status', '!=', 'TRANSFERRED')
            ->orderBy('id')
            ->pluck('status', 'id');

        if ($invalid->isEmpty()) {
            return;
        }

        throw new RuntimeException(
            'CMS-1 only permits immutable intake status TRANSFERRED. '
            .'Resolve unsupported historical statuses before retrying migration: '
            .$invalid->map(
                fn (string $status, int|string $id): string => "{$id}={$status}",
            )->implode(', '),
        );
    }
};
