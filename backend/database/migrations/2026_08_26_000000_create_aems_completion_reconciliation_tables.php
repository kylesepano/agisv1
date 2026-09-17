<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aems_completion_transfer_manifests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('audit_report_id')->nullable()->constrained('audit_reports')->restrictOnDelete();
            $table->foreignId('audit_report_version_id')->nullable()->constrained('audit_report_versions')->restrictOnDelete();
            $table->string('manifest_code', 120)->unique();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->unsignedInteger('expected_count')->default(0);
            $table->unsignedInteger('transferred_count')->default(0);
            $table->unsignedInteger('excluded_count')->default(0);
            $table->unsignedInteger('exception_count')->default(0);
            $table->json('manifest_snapshot_json')->nullable();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->useCurrent();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('reconciliation_comment')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['audit_engagement_id', 'audit_report_version_id'], 'aems_transfer_manifest_report_unique');
        });

        Schema::create('aems_completion_transfer_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manifest_id')->constrained('aems_completion_transfer_manifests')->cascadeOnDelete();
            $table->foreignId('audit_recommendation_id')->nullable()->constrained('audit_recommendations')->restrictOnDelete();
            $table->string('exception_code', 60);
            $table->text('message');
            $table->string('status', 20)->default('OPEN')->index();
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['manifest_id', 'audit_recommendation_id', 'exception_code'], 'aems_transfer_exception_unique');
        });

        Schema::create('aems_effort_reconciliations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('provider_mode', 40);
            $table->string('status', 30)->default('DRAFT')->index();
            $table->decimal('planned_person_days', 10, 2)->default(0);
            $table->decimal('aems_actual_person_days', 10, 2)->default(0);
            $table->decimal('provider_actual_person_days', 10, 2)->nullable();
            $table->decimal('variance_person_days', 10, 2)->default(0);
            $table->json('source_snapshot_json')->nullable();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['audit_engagement_id', 'version_number'], 'aems_effort_reconciliation_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_effort_reconciliations');
        Schema::dropIfExists('aems_completion_transfer_exceptions');
        Schema::dropIfExists('aems_completion_transfer_manifests');
    }
};
