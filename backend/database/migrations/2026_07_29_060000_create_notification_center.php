<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates actionable notifications and per-user delivery preferences. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 80);
            $table->string('category', 30);
            $table->string('priority', 20)->default('NORMAL');
            $table->string('module_code', 20)->default('CORE');
            $table->string('title');
            $table->text('message');
            $table->string('action_url', 1000)->nullable();
            $table->string('action_label', 100)->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_code', 150)->nullable();
            $table->string('dedupe_key', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->unique(['recipient_id', 'dedupe_key']);
            $table->index(['recipient_id', 'archived_at', 'read_at']);
            $table->index(['recipient_id', 'category', 'created_at']);
            $table->index(['module_code', 'subject_type', 'subject_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('workflow_enabled')->default(true);
            $table->boolean('assignments_enabled')->default(true);
            $table->boolean('due_dates_enabled')->default(true);
            $table->boolean('system_enabled')->default(true);
            $table->boolean('email_enabled')->default(false);
            $table->string('digest_frequency', 20)->default('IMMEDIATE');
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
