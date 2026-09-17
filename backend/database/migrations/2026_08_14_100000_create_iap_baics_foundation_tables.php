<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates the BAICS foundation: controlled cycles, source lineage, assignments, and snapshots. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_baics_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('family_uuid')->index();
            $table->string('assessment_code', 80)->unique();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedSmallInteger('assessment_year');
            $table->string('name');
            $table->string('status', 40)->default('DRAFT')->index();
            $table->foreignId('responsible_office_id')->constrained('offices')->restrictOnDelete();
            $table->text('scope_summary');
            $table->text('objectives');
            $table->text('boundaries')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('limitations')->nullable();
            $table->text('methodology')->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();
            $table->date('review_date')->nullable();
            $table->date('report_date')->nullable();
            $table->string('legacy_status', 40)->nullable();
            $table->text('legacy_reason')->nullable();
            $table->foreignId('legacy_authority_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('legacy_expires_at')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('iap_baics_assessments')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assessment_year', 'status']);
            $table->index(['responsible_office_id', 'status']);
        });

        Schema::create('iap_baics_scope_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('iap_baics_assessments')->cascadeOnDelete();
            $table->foreignId('audit_universe_item_id')->constrained('iap_audit_universe_items')->restrictOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('audit_area_id')->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('audit_focus_id')->nullable()->constrained('audit_focuses')->restrictOnDelete();
            $table->json('source_snapshot');
            $table->text('scope_notes')->nullable();
            $table->text('boundaries')->nullable();
            $table->text('exclusions')->nullable();
            $table->text('limitations')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['assessment_id', 'audit_universe_item_id'], 'iap_baics_scope_subject_unique');
            $table->index(['office_id', 'audit_area_id', 'audit_focus_id'], 'iap_baics_scope_dimensions_index');
        });

        Schema::create('iap_baics_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('iap_baics_assessments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role_code', 50);
            $table->string('authority_level', 40)->nullable();
            $table->text('assignment_reason')->nullable();
            $table->string('status', 30)->default('ASSIGNED');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['assessment_id', 'status']);
            $table->unique(['assessment_id', 'user_id', 'role_code'], 'iap_baics_assignment_unique');
        });

        Schema::create('iap_baics_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('iap_baics_assessments')->cascadeOnDelete();
            $table->uuid('family_uuid')->index();
            $table->unsignedInteger('version_number');
            $table->string('status', 40);
            $table->json('snapshot');
            $table->string('snapshot_hash', 64);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['assessment_id', 'version_number'], 'iap_baics_version_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_baics_versions');
        Schema::dropIfExists('iap_baics_assignments');
        Schema::dropIfExists('iap_baics_scope_items');
        Schema::dropIfExists('iap_baics_assessments');
    }
};
