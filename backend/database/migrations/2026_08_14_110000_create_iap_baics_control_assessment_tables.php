<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** BAICS-2 control components, assessment methods, evidence links, and exceptions. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_baics_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('iap_baics_assessments')->cascadeOnDelete();
            $table->string('component_code', 50);
            $table->string('status', 40)->default('DRAFT')->index();
            $table->text('conclusion')->nullable();
            $table->text('supporting_summary')->nullable();
            $table->text('limitations')->nullable();
            $table->foreignId('assessor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('immutable_at')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['assessment_id', 'component_code'], 'iap_baics_component_unique');
            $table->index(['assessment_id', 'status']);
        });

        Schema::create('iap_baics_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->constrained('iap_baics_components')->cascadeOnDelete();
            $table->uuid('family_uuid')->index();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('method_type', 70);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('process_reference', 255)->nullable();
            $table->date('performed_on')->nullable();
            $table->text('procedure')->nullable();
            $table->text('result')->nullable();
            $table->text('limitations')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('immutable_at')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('iap_baics_methods')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['component_id', 'method_type'], 'iap_baics_method_type_index');
        });

        Schema::create('iap_baics_component_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->constrained('iap_baics_components')->cascadeOnDelete();
            $table->string('component_code', 50);
            $table->unsignedInteger('version_number');
            $table->string('status', 40);
            $table->json('snapshot');
            $table->string('snapshot_hash', 64);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['component_id', 'version_number'], 'iap_baics_component_version_lookup');
        });

        Schema::create('iap_baics_method_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('method_id')->constrained('iap_baics_methods')->cascadeOnDelete();
            $table->uuid('family_uuid')->index();
            $table->unsignedInteger('version_number');
            $table->string('status', 40);
            $table->json('snapshot');
            $table->string('snapshot_hash', 64);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['method_id', 'version_number'], 'iap_baics_method_version_lookup');
        });

        Schema::create('iap_baics_evidence_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('component_id')->constrained('iap_baics_components')->cascadeOnDelete();
            $table->foreignId('method_id')->nullable()->constrained('iap_baics_methods')->nullOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->string('evidence_role', 60)->default('SUPPORTING');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['component_id', 'method_id', 'document_version_id'], 'iap_baics_evidence_link_unique');
            $table->index(['component_id', 'method_id']);
        });

        Schema::create('iap_baics_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')->constrained('iap_baics_assessments')->cascadeOnDelete();
            $table->foreignId('component_id')->constrained('iap_baics_components')->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('authority_user_id')->constrained('users')->restrictOnDelete();
            $table->text('compensating_evidence');
            $table->date('expiry_date');
            $table->string('status', 40)->default('DRAFT')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('immutable_at')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assessment_id', 'component_id', 'status'], 'iap_baics_exception_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_baics_exceptions');
        Schema::dropIfExists('iap_baics_evidence_links');
        Schema::dropIfExists('iap_baics_method_versions');
        Schema::dropIfExists('iap_baics_component_versions');
        Schema::dropIfExists('iap_baics_methods');
        Schema::dropIfExists('iap_baics_components');
    }
};
