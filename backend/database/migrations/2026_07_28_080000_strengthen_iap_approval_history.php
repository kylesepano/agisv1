<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Strengthens actor, comment, and before/after values in IAP workflow histories. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iap_workflow_events', function (Blueprint $table): void {
            $table->json('old_values')->nullable()->after('comment');
            $table->json('new_values')->nullable()->after('old_values');
        });
    }

    public function down(): void
    {
        Schema::table('iap_workflow_events', function (Blueprint $table): void {
            $table->dropColumn(['old_values', 'new_values']);
        });
    }
};
