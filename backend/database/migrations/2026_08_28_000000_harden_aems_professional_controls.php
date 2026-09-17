<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_findings', function (Blueprint $table): void {
            $table->string('direct_creation_reason', 80)
                ->nullable()
                ->after('source_issue_id');
            $table->text('direct_creation_authority')
                ->nullable()
                ->after('direct_creation_reason');
            $table->foreignId('direct_creation_by')
                ->nullable()
                ->after('direct_creation_authority')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('direct_creation_at')
                ->nullable()
                ->after('direct_creation_by');
        });

        // SQLite rebuilds audit_findings when adding columns and can lose the
        // predicate from the existing partial current-revision index. Restore
        // that professional-control invariant after the rebuild.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS aem_current_finding_family_unique');
            DB::statement(
                'CREATE UNIQUE INDEX aem_current_finding_family_unique
                 ON audit_findings (audit_engagement_id, finding_family_uuid)
                 WHERE is_current_revision = true AND deleted_at IS NULL',
            );
        }
    }

    public function down(): void
    {
        Schema::table('audit_findings', function (Blueprint $table): void {
            $table->dropForeign(['direct_creation_by']);
            $table->dropColumn([
                'direct_creation_reason',
                'direct_creation_authority',
                'direct_creation_by',
                'direct_creation_at',
            ]);
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS aem_current_finding_family_unique');
            DB::statement(
                'CREATE UNIQUE INDEX aem_current_finding_family_unique
                 ON audit_findings (audit_engagement_id, finding_family_uuid)
                 WHERE is_current_revision = true AND deleted_at IS NULL',
            );
        }
    }
};
