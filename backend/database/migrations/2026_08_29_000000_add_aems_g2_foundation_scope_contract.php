<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AEMS-G2 foundation contract.
 *
 * This migration is intentionally additive.  Existing office and IAP lineage
 * columns remain compatibility fields; the review table records how legacy
 * office rows were reconciled before the stricter one-office invariant is
 * applied to new and changed records.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->string('iap_risk_source_type', 40)->nullable()->after('iap_risk_assessment_id');
            $table->foreignId('iap_legacy_risk_assessment_id')
                ->nullable()
                ->after('iap_risk_source_type')
                ->constrained('iap_risk_assessments')
                ->nullOnDelete();
            $table->string('special_authority_class', 20)->default('SPECIAL')->after('special_authority_type_code');
            $table->text('scope_boundaries')->nullable()->after('scope');
            $table->text('scope_limitations')->nullable()->after('scope_boundaries');
            $table->json('scope_source_variance')->nullable()->after('scope_limitations');
            $table->index(['iap_risk_source_type', 'source_type'], 'aem_iap_risk_source_idx');
        });

        foreach (['audit_engagement_audit_areas', 'audit_engagement_audit_focuses'] as $pivot) {
            Schema::table($pivot, function (Blueprint $table): void {
                $table->json('coverage_metadata')->nullable();
            });
        }

        if (! Schema::hasTable('aems_engagement_scope_backfill_reviews')) {
            Schema::create('aems_engagement_scope_backfill_reviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('audit_engagement_id')
                    ->constrained('audit_engagements')
                    ->cascadeOnDelete();
                $table->unsignedInteger('office_count')->default(0);
                $table->json('legacy_office_ids')->nullable();
                $table->foreignId('canonical_office_id')
                    ->nullable()
                    ->constrained('offices')
                    ->nullOnDelete();
                $table->string('resolution_status', 40)->index();
                $table->text('resolution_notes')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->unique('audit_engagement_id', 'aem_scope_backfill_review_unique');
            });
        }

        $this->backfillOfficeAndRiskLineage();
        $this->addOneOfficeIndex();
        $this->addPostgresIntegrityChecks();
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS aem_engagement_one_office_unique');
            DB::statement('DROP INDEX IF EXISTS aem_iap_risk_source_idx');
        }
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE audit_engagements DROP CONSTRAINT IF EXISTS aem_engagement_office_required');
            DB::statement('ALTER TABLE audit_engagements DROP CONSTRAINT IF EXISTS aem_engagement_status_check');
            DB::statement('ALTER TABLE audit_engagements DROP CONSTRAINT IF EXISTS aem_engagement_authority_class_check');
        }

        Schema::dropIfExists('aems_engagement_scope_backfill_reviews');
        foreach (['audit_engagement_audit_areas', 'audit_engagement_audit_focuses'] as $pivot) {
            Schema::table($pivot, function (Blueprint $table): void {
                $table->dropColumn('coverage_metadata');
            });
        }
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('iap_legacy_risk_assessment_id');
            $table->dropColumn([
                'iap_risk_source_type',
                'special_authority_class',
                'scope_boundaries',
                'scope_limitations',
                'scope_source_variance',
            ]);
        });
    }

    private function backfillOfficeAndRiskLineage(): void
    {
        DB::table('audit_engagements')
            ->select(['id', 'source_type', 'iap_plan_engagement_id', 'iap_risk_assessment_id'])
            ->orderBy('id')
            ->get()
            ->each(function (object $engagement): void {
                $officeIds = DB::table('audit_engagement_offices')
                    ->where('audit_engagement_id', $engagement->id)
                    ->orderByDesc('is_primary')
                    ->orderBy('office_id')
                    ->pluck('office_id')
                    ->map(fn ($id): int => (int) $id)
                    ->values();
                $canonicalOfficeId = $officeIds->first();
                $status = $officeIds->count() === 0
                    ? 'REQUIRES_REVIEW'
                    : ($officeIds->count() === 1 ? 'RESOLVED' : 'RESOLVED_WITH_LEGACY_DUPLICATES');

                if ($canonicalOfficeId !== null && $officeIds->count() > 1) {
                    DB::table('audit_engagement_offices')
                        ->where('audit_engagement_id', $engagement->id)
                        ->where('office_id', '<>', $canonicalOfficeId)
                        ->delete();
                }
                if ($canonicalOfficeId !== null) {
                    DB::table('audit_engagement_offices')
                        ->where('audit_engagement_id', $engagement->id)
                        ->update(['is_primary' => false]);
                    DB::table('audit_engagement_offices')
                        ->where('audit_engagement_id', $engagement->id)
                        ->where('office_id', $canonicalOfficeId)
                        ->update(['is_primary' => true]);
                }

                DB::table('aems_engagement_scope_backfill_reviews')->updateOrInsert(
                    ['audit_engagement_id' => $engagement->id],
                    [
                        'office_count' => $officeIds->count(),
                        'legacy_office_ids' => json_encode($officeIds->all()),
                        'canonical_office_id' => $canonicalOfficeId,
                        'resolution_status' => $status,
                        'resolution_notes' => $officeIds->count() > 1
                            ? 'Canonical office retained; legacy extra office pivots preserved in this review record.'
                            : ($officeIds->isEmpty() ? 'No office was present; an authorized reviewer must resolve scope before activation.' : 'Single office confirmed during G2 backfill.'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $riskSource = null;
                $legacyRiskId = null;
                if ($engagement->iap_plan_engagement_id) {
                    $source = DB::table('iap_plan_engagements')
                        ->select(['risk_assessment_id', 'universe_risk_assessment_id'])
                        ->where('id', $engagement->iap_plan_engagement_id)
                        ->first();
                    if ($source?->universe_risk_assessment_id) {
                        $riskSource = 'UNIVERSE_RISK_ASSESSMENT';
                    } elseif ($source?->risk_assessment_id) {
                        $riskSource = 'LEGACY_RISK_ASSESSMENT';
                        $legacyRiskId = (int) $source->risk_assessment_id;
                    }
                }
                if ($riskSource === null && $engagement->iap_risk_assessment_id) {
                    $riskSource = 'UNIVERSE_RISK_ASSESSMENT';
                }

                DB::table('audit_engagements')
                    ->where('id', $engagement->id)
                    ->update([
                        'engagement_office_id' => $canonicalOfficeId,
                        'iap_risk_source_type' => $riskSource,
                        'iap_legacy_risk_assessment_id' => $legacyRiskId,
                    ]);
            });
    }

    private function addOneOfficeIndex(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX aem_engagement_one_office_unique '
                .'ON audit_engagement_offices (audit_engagement_id)',
            );
        }
    }

    private function addPostgresIntegrityChecks(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement(
            "ALTER TABLE audit_engagements ADD CONSTRAINT aem_engagement_status_check
             CHECK (status IN ('DRAFT', 'AUTHORIZATION_PREPARATION', 'RETURNED_FOR_REVISION',
             'AUTHORIZED', 'ENGAGEMENT_PLANNING', 'ENTRY_CONFERENCE', 'FIELDWORK',
             'FINDINGS_COMMUNICATION', 'REPORTING', 'ISSUED', 'CLOSURE_REVIEW',
             'COMPLETED', 'CLOSED', 'SUSPENDED', 'CANCELLED'))",
        );
        DB::statement(
            "ALTER TABLE audit_engagements ADD CONSTRAINT aem_engagement_authority_class_check
             CHECK (special_authority_class IN ('SPECIAL', 'EMERGENCY'))",
        );
        $missing = DB::table('audit_engagements')->whereNull('engagement_office_id')->exists();
        DB::statement(
            'ALTER TABLE audit_engagements ADD CONSTRAINT aem_engagement_office_required '
            .'CHECK (engagement_office_id IS NOT NULL)'.($missing ? ' NOT VALID' : ''),
        );
    }
};
