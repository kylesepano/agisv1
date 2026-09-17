<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Links Annual Plan engagements to their source universe, assessment, and ranking. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_audit_plans', function (Blueprint $table): void {
            $table->unsignedBigInteger('prioritization_run_id')
                ->nullable()
                ->after('planning_period_type_id')
                ->index();
        });
        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('internal_audit_plans', function (Blueprint $table): void {
                $table->foreign('prioritization_run_id')
                    ->references('id')
                    ->on('iap_prioritization_runs')
                    ->restrictOnDelete();
            });
        }

        Schema::table('iap_plan_engagements', function (Blueprint $table): void {
            $table->foreignId('prioritization_item_id')
                ->nullable()
                ->after('risk_assessment_id')
                ->constrained('iap_prioritization_items')
                ->restrictOnDelete();
            $table->foreignId('audit_universe_item_id')
                ->nullable()
                ->after('prioritization_item_id')
                ->constrained('iap_audit_universe_items')
                ->restrictOnDelete();
            $table->foreignId('universe_risk_assessment_id')
                ->nullable()
                ->after('audit_universe_item_id')
                ->constrained('iap_universe_risk_assessments')
                ->restrictOnDelete();
            $table->decimal('source_inherent_risk_score', 5, 2)->nullable();
            $table->decimal('source_residual_risk_score', 5, 2)->nullable();
            $table->decimal('source_priority_score', 6, 2)->nullable();
            $table->string('source_risk_level_code', 30)->nullable();
            $table->string('source_decision', 30)->nullable();
            $table->unsignedInteger('source_final_rank')->nullable();
            $table->unsignedSmallInteger('target_quarter')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->foreignId('imported_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->unique(
                ['plan_id', 'prioritization_item_id'],
                'iap_plan_prioritization_item_unique',
            );
            $table->unique(
                ['plan_id', 'audit_universe_item_id'],
                'iap_plan_universe_item_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('iap_plan_engagements', function (Blueprint $table): void {
            $table->dropUnique('iap_plan_prioritization_item_unique');
            $table->dropUnique('iap_plan_universe_item_unique');
            $table->dropConstrainedForeignId('imported_by');
            $table->dropConstrainedForeignId('universe_risk_assessment_id');
            $table->dropConstrainedForeignId('audit_universe_item_id');
            $table->dropConstrainedForeignId('prioritization_item_id');
            $table->dropColumn([
                'source_inherent_risk_score',
                'source_residual_risk_score',
                'source_priority_score',
                'source_risk_level_code',
                'source_decision',
                'source_final_rank',
                'target_quarter',
                'imported_at',
            ]);
        });

        Schema::table('internal_audit_plans', function (Blueprint $table): void {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['prioritization_run_id']);
            }
            $table->dropColumn('prioritization_run_id');
        });
    }
};
