<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_baics_exception_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('exception_id')->constrained('iap_baics_exceptions')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 40);
            $table->json('snapshot');
            $table->string('snapshot_hash', 64);
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['exception_id', 'version_number'], 'iap_baics_exception_version_lookup');
        });
    }

    public function down(): void { Schema::dropIfExists('iap_baics_exception_versions'); }
};
