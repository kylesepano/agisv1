<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates risk periods, weighted criteria, assessments, scores, evidence, and events. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_risk_periods', function (Blueprint $table): void {
            $table->id();
            $table->string('period_code', 60)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('assessment_year')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->text('instructions')->nullable();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('opened_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('iap_risk_period_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('iap_risk_periods')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('master_list_items')->restrictOnDelete();
            $table->decimal('weight', 5, 2);
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->timestamps();
            $table->unique(['period_id', 'criterion_id'], 'iap_risk_period_criterion_unique');
        });

        Schema::create('iap_universe_risk_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('iap_risk_periods')->cascadeOnDelete();
            $table->foreignId('audit_universe_item_id')
                ->constrained('iap_audit_universe_items')->restrictOnDelete();
            $table->foreignId('assessed_by')->constrained('users')->restrictOnDelete();
            $table->date('assessment_date');
            $table->decimal('control_effectiveness_percent', 5, 2)->default(0);
            $table->decimal('inherent_risk_score', 5, 2)->default(0);
            $table->decimal('residual_risk_score', 5, 2)->default(0);
            $table->foreignId('inherent_risk_level_id')
                ->nullable()->constrained('master_list_items')->restrictOnDelete();
            $table->foreignId('residual_risk_level_id')
                ->nullable()->constrained('master_list_items')->restrictOnDelete();
            $table->text('control_effectiveness_notes');
            $table->text('justification');
            $table->text('evidence_summary')->nullable();
            $table->string('status', 40)->default('DRAFT')->index();
            $table->text('validation_comment')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(
                ['period_id', 'audit_universe_item_id'],
                'iap_period_universe_assessment_unique',
            );
        });

        Schema::create('iap_universe_risk_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')
                ->constrained('iap_universe_risk_assessments')->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('master_list_items')->restrictOnDelete();
            $table->decimal('criterion_weight', 5, 2);
            $table->decimal('rating', 3, 2);
            $table->decimal('weighted_score', 5, 2);
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique(['assessment_id', 'criterion_id'], 'iap_universe_score_unique');
        });

        Schema::create('iap_risk_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_id')
                ->constrained('iap_universe_risk_assessments')->cascadeOnDelete();
            $table->string('original_file_name');
            $table->string('storage_path');
            $table->string('mime_type', 120);
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('checksum_sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('iap_risk_period_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('period_id')->constrained('iap_risk_periods')->cascadeOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->unsignedInteger('period_lock_version');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['period_id', 'created_at'], 'iap_risk_period_event_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_risk_period_events');
        Schema::dropIfExists('iap_risk_evidence');
        Schema::dropIfExists('iap_universe_risk_scores');
        Schema::dropIfExists('iap_universe_risk_assessments');
        Schema::dropIfExists('iap_risk_period_criteria');
        Schema::dropIfExists('iap_risk_periods');
    }
};
