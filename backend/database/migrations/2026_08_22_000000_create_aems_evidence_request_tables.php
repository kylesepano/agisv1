<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aems_evidence_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('request_family_uuid');
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->string('request_code', 100);
            $table->string('title', 255);
            $table->text('purpose');
            $table->foreignId('requested_from_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->foreignId('requested_from_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->unsignedInteger('current_version_number')->default(1);
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('partially_received_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('assessed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('closure_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['audit_engagement_id', 'request_code'], 'aems_evidence_request_code_unique');
            $table->index(['audit_engagement_id', 'status', 'deleted_at'], 'aems_evidence_request_scope_idx');
        });

        Schema::create('aems_evidence_request_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidence_request_id')->constrained('aems_evidence_requests')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title', 255);
            $table->text('purpose');
            $table->foreignId('requested_from_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->foreignId('requested_from_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->json('requested_items')->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['evidence_request_id', 'version_number'], 'aems_evidence_request_version_unique');
        });

        Schema::create('aems_evidence_request_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidence_request_id')->constrained('aems_evidence_requests')->cascadeOnDelete();
            $table->foreignId('audit_evidence_id')->constrained('audit_evidence')->restrictOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->text('receipt_notes')->nullable();
            $table->timestamps();
            $table->unique(['evidence_request_id', 'audit_evidence_id', 'document_version_id'], 'aems_evidence_request_evidence_unique');
            $table->index(['evidence_request_id', 'received_at'], 'aems_evidence_request_received_idx');
        });

        Schema::create('aems_evidence_assessments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('assessment_family_uuid');
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->restrictOnDelete();
            $table->foreignId('audit_evidence_id')->constrained('audit_evidence')->restrictOnDelete();
            $table->foreignId('evidence_request_id')->nullable()->constrained('aems_evidence_requests')->restrictOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->unsignedInteger('version_number')->default(1);
            $table->foreignId('supersedes_assessment_id')->nullable()->constrained('aems_evidence_assessments')->restrictOnDelete();
            $table->boolean('is_current_revision')->default(true);
            $table->string('status', 30)->default('ASSESSED')->index();
            $table->string('sufficiency', 30)->nullable();
            $table->string('appropriateness', 30)->nullable();
            $table->string('relevance', 30)->nullable();
            $table->string('reliability', 30)->nullable();
            $table->string('competence', 30)->nullable();
            $table->string('accuracy', 30)->nullable();
            $table->string('completeness', 30)->nullable();
            $table->string('corroboration', 30)->nullable();
            $table->string('contradiction', 30)->nullable();
            $table->string('authenticity', 30)->nullable();
            $table->string('integrity', 30)->nullable();
            $table->string('confidentiality', 30)->nullable();
            $table->boolean('is_restricted')->default(false);
            $table->text('access_restrictions')->nullable();
            $table->text('limitations')->nullable();
            $table->text('evidence_gaps')->nullable();
            $table->boolean('exception_required')->default(false);
            $table->text('exception_reason')->nullable();
            $table->foreignId('exception_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('exception_approved_at')->nullable();
            $table->text('exception_approval_comment')->nullable();
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assessed_at')->useCurrent();
            $table->text('change_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['assessment_family_uuid', 'version_number'], 'aems_evidence_assessment_version_unique');
            $table->index(['audit_engagement_id', 'audit_evidence_id', 'is_current_revision'], 'aems_evidence_assessment_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_evidence_assessments');
        Schema::dropIfExists('aems_evidence_request_evidence');
        Schema::dropIfExists('aems_evidence_request_versions');
        Schema::dropIfExists('aems_evidence_requests');
    }
};
