<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('engagement_retention_records', function (Blueprint $table): void {
            $table->string('legal_hold_release_reference', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('engagement_retention_records', function (Blueprint $table): void {
            $table->dropColumn('legal_hold_release_reference');
        });
    }
};
