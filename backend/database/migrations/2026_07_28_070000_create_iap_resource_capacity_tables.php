<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates temporary auditor capacity, skill, and unavailability records for IAP. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_auditor_unavailability', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unavailability_type_id')
                ->constrained('master_list_items')->restrictOnDelete();
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['user_id', 'start_date', 'end_date'], 'iap_unavailable_user_dates_idx');
        });

        Schema::create('iap_auditor_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')
                ->constrained('master_list_items')->restrictOnDelete();
            $table->string('proficiency_level', 20)->default('INTERMEDIATE');
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'specialization_id']);
        });

        Schema::create('iap_engagement_skill_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_engagement_id')
                ->constrained('iap_plan_engagements')->cascadeOnDelete();
            $table->foreignId('specialization_id')
                ->constrained('master_list_items')->restrictOnDelete();
            $table->unsignedSmallInteger('minimum_auditors')->default(1);
            $table->string('minimum_proficiency', 20)->default('INTERMEDIATE');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(
                ['plan_engagement_id', 'specialization_id'],
                'iap_engagement_specialization_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_engagement_skill_requirements');
        Schema::dropIfExists('iap_auditor_skills');
        Schema::dropIfExists('iap_auditor_unavailability');
    }
};
