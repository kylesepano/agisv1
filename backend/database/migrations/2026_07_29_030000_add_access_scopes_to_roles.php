<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds configurable module, office, and engagement scopes to access roles. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->string('office_access_scope', 30)->default('ALL')->after('is_active');
            $table->string('engagement_access_scope', 30)->default('ALL')->after('office_access_scope');
        });

        DB::table('roles')
            ->where('code', 'agis_user')
            ->update(['engagement_access_scope' => 'ASSIGNED']);

        DB::table('roles')
            ->where('code', 'auditee_representative')
            ->update([
                'office_access_scope' => 'OWN_OFFICE',
                'engagement_access_scope' => 'ASSIGNED',
            ]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn([
                'office_access_scope',
                'engagement_access_scope',
            ]);
        });
    }
};
