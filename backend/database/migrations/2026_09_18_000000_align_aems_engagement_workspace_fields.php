<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the WF-SCR-200 engagement-creation fields without removing legacy data.
 * All columns are nullable so existing AEMS records remain valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->foreignId('requesting_office_id')
                ->nullable()
                ->after('engagement_office_id')
                ->constrained('offices')
                ->nullOnDelete();
            $table->date('special_authority_received_date')
                ->nullable()
                ->after('special_authority_date');
            $table->unsignedSmallInteger('audit_year')
                ->nullable()
                ->after('engagement_approach_id');
            $table->date('period_covered_start_date')
                ->nullable()
                ->after('exclusions');
            $table->date('period_covered_end_date')
                ->nullable()
                ->after('period_covered_start_date');

            $table->index('audit_year', 'aem_engagement_audit_year_idx');
            $table->index(
                ['period_covered_start_date', 'period_covered_end_date'],
                'aem_engagement_period_covered_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->dropForeign(['requesting_office_id']);
            $table->dropIndex('aem_engagement_audit_year_idx');
            $table->dropIndex('aem_engagement_period_covered_idx');
            $table->dropColumn([
                'requesting_office_id',
                'special_authority_received_date',
                'audit_year',
                'period_covered_start_date',
                'period_covered_end_date',
            ]);
        });
    }
};
