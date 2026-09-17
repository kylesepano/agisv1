<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors the PostgreSQL partial active-version index with a portable nullable
 * slot so the invariant also holds in SQLite-backed automated tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_action_plan_versions', function (Blueprint $table): void {
            $table->string('active_slot', 20)
                ->nullable()
                ->default('ACTIVE')
                ->after('status_code');
        });

        DB::table('cms_action_plan_versions')
            ->whereIn('status_code', ['RETURNED', 'ACCEPTED'])
            ->update(['active_slot' => null]);

        Schema::table('cms_action_plan_versions', function (Blueprint $table): void {
            $table->unique(
                ['cms_corrective_action_plan_id', 'active_slot'],
                'cms_action_plan_family_active_slot_unique',
            );
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE cms_action_plan_versions
                 ADD CONSTRAINT cms_action_plan_active_slot_check
                 CHECK (
                    (status_code IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW') AND active_slot = 'ACTIVE')
                    OR
                    (status_code IN ('RETURNED', 'ACCEPTED') AND active_slot IS NULL)
                 )",
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE cms_action_plan_versions
                 DROP CONSTRAINT IF EXISTS cms_action_plan_active_slot_check',
            );
        }
        Schema::table('cms_action_plan_versions', function (Blueprint $table): void {
            $table->dropUnique('cms_action_plan_family_active_slot_unique');
            $table->dropColumn('active_slot');
        });
    }
};
