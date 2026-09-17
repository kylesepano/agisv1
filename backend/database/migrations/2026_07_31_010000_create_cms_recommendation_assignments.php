<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds historical Compliance Monitor assignments without changing CMS case
 * workflow status or the immutable AEMS intake envelope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_recommendation_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')
                ->constrained('cms_recommendation_cases')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('assignment_role_code', 60);
            $table->text('assignment_reason')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('ended_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->text('end_reason')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(
                ['cms_recommendation_case_id', 'assigned_at'],
                'cms_assignment_case_history_idx',
            );
            $table->index(
                ['user_id', 'is_current'],
                'cms_assignment_user_current_idx',
            );
        });

        $truth = DB::getDriverName() === 'pgsql' ? 'true' : '1';
        DB::statement(
            "CREATE UNIQUE INDEX cms_one_current_monitor_per_case
             ON cms_recommendation_assignments (cms_recommendation_case_id)
             WHERE is_current = {$truth}
               AND assignment_role_code = 'COMPLIANCE_MONITOR'",
        );
        DB::statement(
            "CREATE UNIQUE INDEX cms_no_duplicate_current_monitor
             ON cms_recommendation_assignments (
                 cms_recommendation_case_id,
                 user_id,
                 assignment_role_code
             )
             WHERE is_current = {$truth}",
        );

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                "ALTER TABLE cms_recommendation_assignments
                 ADD CONSTRAINT cms_assignment_role_check
                 CHECK (assignment_role_code = 'COMPLIANCE_MONITOR')",
            );
            DB::statement(
                'ALTER TABLE cms_recommendation_assignments
                 ADD CONSTRAINT cms_assignment_current_state_check
                 CHECK (
                     (is_current = true AND ended_by IS NULL AND ended_at IS NULL AND end_reason IS NULL)
                     OR
                     (is_current = false AND ended_by IS NOT NULL AND ended_at IS NOT NULL
                      AND end_reason IS NOT NULL)
                 )',
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_recommendation_assignments');
    }
};
