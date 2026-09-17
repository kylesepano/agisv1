<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_evidence', function (Blueprint $table): void {
            $table->boolean('assessment_required')->default(false)->after('status');
            $table->index(['audit_engagement_id', 'assessment_required', 'status'], 'aem_evidence_assessment_requirement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('audit_evidence', function (Blueprint $table): void {
            $table->dropIndex('aem_evidence_assessment_requirement_idx');
            $table->dropColumn('assessment_required');
        });
    }
};
