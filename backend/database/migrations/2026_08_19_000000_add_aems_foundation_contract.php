<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the AEMS-1A foundation projections without removing the legacy status
 * or engagement-office pivot used by existing APIs and historical records.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private array $phaseByStatus = [
        'DRAFT' => 'FOUNDATION',
        'AUTHORIZATION_PREPARATION' => 'FOUNDATION',
        'RETURNED_FOR_REVISION' => 'FOUNDATION',
        'AUTHORIZED' => 'FOUNDATION',
        'ENGAGEMENT_PLANNING' => 'PLANNING',
        'ENTRY_CONFERENCE' => 'CONFERENCES',
        'FIELDWORK' => 'EXECUTION',
        'FINDINGS_COMMUNICATION' => 'ISSUES_AFR',
        'REPORTING' => 'REPORTING',
        'ISSUED' => 'REPORTING',
        'CLOSURE_REVIEW' => 'COMPLETION_TRANSFER',
        'CLOSED' => 'CLOSURE',
        'SUSPENDED' => 'FOUNDATION',
        'CANCELLED' => 'CLOSURE',
    ];

    /** @var array<string, string> */
    private array $administrativeStatusByStatus = [
        'DRAFT' => 'DRAFT',
        'AUTHORIZATION_PREPARATION' => 'ACTIVE',
        'RETURNED_FOR_REVISION' => 'RETURNED',
        'AUTHORIZED' => 'ACTIVE',
        'ENGAGEMENT_PLANNING' => 'ACTIVE',
        'ENTRY_CONFERENCE' => 'ACTIVE',
        'FIELDWORK' => 'ACTIVE',
        'FINDINGS_COMMUNICATION' => 'ACTIVE',
        'REPORTING' => 'ACTIVE',
        'ISSUED' => 'ISSUED',
        'CLOSURE_REVIEW' => 'ACTIVE',
        'CLOSED' => 'CLOSED',
        'SUSPENDED' => 'SUSPENDED',
        'CANCELLED' => 'CANCELLED',
    ];

    public function up(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->foreignId('engagement_office_id')
                ->nullable()
                ->after('source_snapshot')
                ->constrained('offices')
                ->restrictOnDelete();
            $table->string('phase', 40)->default('FOUNDATION')->after('status')->index();
            $table->string('administrative_status', 40)
                ->default('DRAFT')
                ->after('phase')
                ->index();
            $table->index(
                ['phase', 'administrative_status'],
                'aem_engagement_phase_admin_status_idx',
            );
        });

        $this->backfillProjectionAndCanonicalOffice();
        $this->normalizePrimaryOfficeFlags();
        $this->addPrimaryOfficeIntegrityIndex();
        $this->addPostgresChecks();
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS aem_engagement_primary_office_unique');
        }
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE audit_engagements DROP CONSTRAINT IF EXISTS aem_engagement_phase_check');
            DB::statement('ALTER TABLE audit_engagements DROP CONSTRAINT IF EXISTS aem_engagement_admin_status_check');
        }

        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->dropForeign(['engagement_office_id']);
            $table->dropIndex('aem_engagement_phase_admin_status_idx');
            $table->dropColumn([
                'engagement_office_id',
                'phase',
                'administrative_status',
            ]);
        });
    }

    private function backfillProjectionAndCanonicalOffice(): void
    {
        DB::table('audit_engagements')
            ->select(['id', 'status', 'suspended_from_status', 'deleted_at'])
            ->orderBy('id')
            ->get()
            ->each(function (object $engagement): void {
                $officeId = DB::table('audit_engagement_offices')
                    ->where('audit_engagement_id', $engagement->id)
                    ->orderByDesc('is_primary')
                    ->orderBy('office_id')
                    ->value('office_id');

                $status = (string) $engagement->status;
                $phaseStatus = $status === 'SUSPENDED'
                    ? (string) ($engagement->suspended_from_status ?: 'DRAFT')
                    : $status;
                $phase = $this->phaseByStatus[$phaseStatus] ?? 'FOUNDATION';
                $administrativeStatus = $engagement->deleted_at !== null
                    ? 'ARCHIVED'
                    : ($this->administrativeStatusByStatus[$status] ?? 'ACTIVE');

                DB::table('audit_engagements')
                    ->where('id', $engagement->id)
                    ->update([
                        'engagement_office_id' => $officeId,
                        'phase' => $phase,
                        'administrative_status' => $administrativeStatus,
                    ]);
            });
    }

    private function normalizePrimaryOfficeFlags(): void
    {
        DB::table('audit_engagements')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $engagement): void {
                $offices = DB::table('audit_engagement_offices')
                    ->where('audit_engagement_id', $engagement->id)
                    ->orderByDesc('is_primary')
                    ->orderBy('office_id')
                    ->pluck('office_id');

                if ($offices->isEmpty()) {
                    return;
                }

                $primaryOfficeId = (int) $offices->first();
                DB::table('audit_engagement_offices')
                    ->where('audit_engagement_id', $engagement->id)
                    ->update(['is_primary' => false]);
                DB::table('audit_engagement_offices')
                    ->where('audit_engagement_id', $engagement->id)
                    ->where('office_id', $primaryOfficeId)
                    ->update(['is_primary' => true]);
            });
    }

    private function addPrimaryOfficeIntegrityIndex(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            $predicate = $driver === 'pgsql' ? 'is_primary = TRUE' : 'is_primary = 1';
            DB::statement(
                'CREATE UNIQUE INDEX aem_engagement_primary_office_unique '
                ."ON audit_engagement_offices (audit_engagement_id) WHERE {$predicate}",
            );
        }
    }

    private function addPostgresChecks(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "ALTER TABLE audit_engagements ADD CONSTRAINT aem_engagement_phase_check
             CHECK (phase IN ('FOUNDATION', 'PLANNING', 'EXECUTION', 'ISSUES_AFR',
             'CONFERENCES', 'REPORTING', 'COMPLETION_TRANSFER', 'CLOSURE'))",
        );
        DB::statement(
            "ALTER TABLE audit_engagements ADD CONSTRAINT aem_engagement_admin_status_check
             CHECK (administrative_status IN ('DRAFT', 'ACTIVE', 'RETURNED', 'ISSUED',
             'SUSPENDED', 'CANCELLED', 'CLOSED', 'ARCHIVED'))",
        );
    }
};
