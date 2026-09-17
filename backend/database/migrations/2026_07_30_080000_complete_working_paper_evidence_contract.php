<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completes the immutable Working Paper and Evidence contract without changing
 * the already-applied AEMS foundation migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('working_papers', function (Blueprint $table): void {
            $table->timestamp('reviewed_at')->nullable()->after('reviewer_id');
            $table->index(
                ['audit_engagement_id', 'status', 'deleted_at'],
                'aem_working_paper_status_idx',
            );
        });

        Schema::table('working_paper_versions', function (Blueprint $table): void {
            $table->text('no_evidence_reason')->nullable()->after('conclusion');
        });

        Schema::table('audit_evidence', function (Blueprint $table): void {
            $table->foreignId('evidence_source_type_id')
                ->nullable()
                ->after('evidence_category_id')
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->index(
                ['audit_engagement_id', 'status', 'deleted_at'],
                'aem_evidence_status_idx',
            );
        });
        $this->restoreSqliteEvidenceCurrentIndex();

        Schema::create('working_paper_version_evidence', function (Blueprint $table): void {
            $table->foreignId('working_paper_version_id')
                ->constrained('working_paper_versions')
                ->cascadeOnDelete();
            $table->foreignId('audit_evidence_id')
                ->constrained('audit_evidence')
                ->restrictOnDelete();
            $table->timestamps();
            $table->primary(
                ['working_paper_version_id', 'audit_evidence_id'],
                'aem_wp_version_evidence_pk',
            );
        });

        // Preserve any pre-existing family-level links by attaching them to the
        // exact latest content version available at migration time.
        DB::table('working_paper_evidence')
            ->orderBy('working_paper_id')
            ->get()
            ->each(function (object $link): void {
                $versionId = DB::table('working_paper_versions')
                    ->where('working_paper_id', $link->working_paper_id)
                    ->orderByDesc('version_number')
                    ->value('id');
                if ($versionId) {
                    DB::table('working_paper_version_evidence')->insertOrIgnore([
                        'working_paper_version_id' => $versionId,
                        'audit_evidence_id' => $link->audit_evidence_id,
                        'created_at' => $link->created_at,
                        'updated_at' => $link->updated_at,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('working_paper_version_evidence');

        Schema::table('audit_evidence', function (Blueprint $table): void {
            $table->dropIndex('aem_evidence_status_idx');
            $table->dropConstrainedForeignId('evidence_source_type_id');
        });
        $this->restoreSqliteEvidenceCurrentIndex();

        Schema::table('working_paper_versions', function (Blueprint $table): void {
            $table->dropColumn('no_evidence_reason');
        });

        Schema::table('working_papers', function (Blueprint $table): void {
            $table->dropIndex('aem_working_paper_status_idx');
            $table->dropColumn('reviewed_at');
        });
    }

    /**
     * SQLite rebuilds a table when a foreign key column is added or removed.
     * Laravel cannot round-trip a partial-index predicate during that rebuild,
     * so restore the original current-revision invariant explicitly.
     */
    private function restoreSqliteEvidenceCurrentIndex(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS aem_current_evidence_unique');
        DB::statement(
            'CREATE UNIQUE INDEX aem_current_evidence_unique
             ON audit_evidence (audit_engagement_id, evidence_code)
             WHERE is_current_revision = true AND deleted_at IS NULL',
        );
    }
};
