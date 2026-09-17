<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AEMS drafts are created before SCR-212 scope completion.  Preserve the
 * one-office invariant for every operational lifecycle state while allowing a
 * Draft to temporarily have no canonical office until Scope is saved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE audit_engagements DROP CONSTRAINT IF EXISTS aem_engagement_office_required');
        DB::statement(
            "ALTER TABLE audit_engagements ADD CONSTRAINT aem_engagement_office_required
             CHECK (status = 'DRAFT' OR engagement_office_id IS NOT NULL) NOT VALID",
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE audit_engagements DROP CONSTRAINT IF EXISTS aem_engagement_office_required');
        DB::statement(
            'ALTER TABLE audit_engagements ADD CONSTRAINT aem_engagement_office_required '
            .'CHECK (engagement_office_id IS NOT NULL) NOT VALID',
        );
    }
};
