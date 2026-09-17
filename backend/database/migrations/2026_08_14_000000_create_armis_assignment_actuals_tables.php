<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Establishes the ARMIS-4A assignment and actual-person-day ledgers. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('armis_engagement_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('assignment_family_uuid')->nullable();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('resource_profile_id')->constrained('armis_resource_profiles')->restrictOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('armis_resource_requirements')->nullOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('armis_engagement_assignments')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->string('assignment_role_code', 40)->index();
            $table->date('assigned_from')->nullable();
            $table->date('assigned_until')->nullable();
            $table->decimal('planned_person_days', 8, 2)->default(0);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['audit_engagement_id', 'status'], 'armis_assignment_engagement_status_idx');
            $table->index(['resource_profile_id', 'assigned_from', 'assigned_until'], 'armis_assignment_profile_dates_idx');
        });

        Schema::create('armis_assignment_competencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained('armis_engagement_assignments')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('master_list_items')->restrictOnDelete();
            $table->string('minimum_proficiency', 20)->default('INTERMEDIATE');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'competency_id'], 'armis_assignment_competency_unique');
        });

        Schema::table('armis_actual_person_days', function (Blueprint $table): void {
            $table->uuid('actual_family_uuid')->nullable()->after('id');
            $table->foreignId('assignment_id')->nullable()->after('resource_profile_id')
                ->constrained('armis_engagement_assignments')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->after('status')->index();
            $table->text('variance_reason')->nullable()->after('notes');
            $table->foreignId('created_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        DB::table('armis_actual_person_days')->orderBy('id')->get(['id'])->each(function (object $row): void {
            DB::table('armis_actual_person_days')->where('id', $row->id)->update([
                'actual_family_uuid' => (string) Str::uuid(),
                'version_number' => 1,
                'is_current_revision' => true,
            ]);
        });

        DB::statement(
            'CREATE UNIQUE INDEX armis_assignment_current_unique ON armis_engagement_assignments '
            . '(audit_engagement_id, resource_profile_id) '
            . 'WHERE is_current_revision = TRUE AND deleted_at IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX armis_actual_current_unique ON armis_actual_person_days '
            . '(assignment_id, period_start, period_end) '
            . 'WHERE is_current_revision = TRUE AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS armis_actual_current_unique');
        DB::statement('DROP INDEX IF EXISTS armis_assignment_current_unique');

        Schema::table('armis_actual_person_days', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('assignment_id');
            $table->dropColumn(['actual_family_uuid', 'is_current_revision', 'variance_reason']);
        });

        Schema::dropIfExists('armis_assignment_competencies');
        Schema::dropIfExists('armis_engagement_assignments');
    }
};
