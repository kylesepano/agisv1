<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds queryable IAP lineage while source_snapshot preserves the historical values. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->foreignId('iap_plan_id')
                ->nullable()
                ->after('iap_plan_engagement_id')
                ->constrained('internal_audit_plans')
                ->restrictOnDelete();
            $table->foreignId('iap_prioritization_item_id')
                ->nullable()
                ->after('iap_plan_id')
                ->constrained('iap_prioritization_items')
                ->restrictOnDelete();
            $table->foreignId('iap_risk_assessment_id')
                ->nullable()
                ->after('iap_prioritization_item_id')
                ->constrained('iap_universe_risk_assessments')
                ->restrictOnDelete();
            $table->foreignId('iap_audit_universe_item_id')
                ->nullable()
                ->after('iap_risk_assessment_id')
                ->constrained('iap_audit_universe_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('iap_audit_universe_item_id');
            $table->dropConstrainedForeignId('iap_risk_assessment_id');
            $table->dropConstrainedForeignId('iap_prioritization_item_id');
            $table->dropConstrainedForeignId('iap_plan_id');
        });
    }
};
