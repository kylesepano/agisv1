<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE audit_engagements DROP CONSTRAINT IF EXISTS aem_engagement_status_check');
        DB::statement(
            "ALTER TABLE audit_engagements ADD CONSTRAINT aem_engagement_status_check
             CHECK (status IN ('DRAFT', 'AUTHORIZATION_PREPARATION', 'RETURNED_FOR_REVISION',
             'AUTHORIZED', 'ENGAGEMENT_PLANNING', 'ENTRY_CONFERENCE', 'FIELDWORK',
             'FINDINGS_COMMUNICATION', 'EXIT_CONFERENCE', 'REPORTING', 'ISSUED', 'CLOSURE_REVIEW',
             'COMPLETED', 'CLOSED', 'SUSPENDED', 'CANCELLED'))",
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE audit_engagements DROP CONSTRAINT IF EXISTS aem_engagement_status_check');
        DB::statement(
            "ALTER TABLE audit_engagements ADD CONSTRAINT aem_engagement_status_check
             CHECK (status IN ('DRAFT', 'AUTHORIZATION_PREPARATION', 'RETURNED_FOR_REVISION',
             'AUTHORIZED', 'ENGAGEMENT_PLANNING', 'ENTRY_CONFERENCE', 'FIELDWORK',
             'FINDINGS_COMMUNICATION', 'REPORTING', 'ISSUED', 'CLOSURE_REVIEW',
             'COMPLETED', 'CLOSED', 'SUSPENDED', 'CANCELLED'))",
        );
    }
};
