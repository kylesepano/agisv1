<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stores the SCR-200 initial AEO and team proposal before controlled assignment. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->json('initial_team_plan')->nullable()->after('audit_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('audit_engagements', function (Blueprint $table): void {
            $table->dropColumn('initial_team_plan');
        });
    }
};
