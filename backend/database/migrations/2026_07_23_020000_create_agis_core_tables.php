<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates the initial Office, Audit Area, Audit Focus, and master-list registries. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('sector', 120)->nullable()->after('acronym');
            $table->string('contact_number', 255)->nullable()->after('sector');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id', 40)->nullable()->unique()->after('office_id');
            $table->string('contact_number', 100)->nullable()->after('position');
            $table->date('birth_date')->nullable()->after('contact_number');
            $table->boolean('is_office_head')->default(false)->index()->after('birth_date');
        });

        Schema::create('audit_areas', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_area_office', function (Blueprint $table) {
            $table->foreignId('audit_area_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['audit_area_id', 'office_id']);
        });

        Schema::create('audit_focuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_area_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['audit_area_id', 'code']);
        });

        Schema::create('master_lists', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('master_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_list_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['master_list_id', 'code']);
        });

        Schema::create('system_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name');
            $table->json('value');
            $table->string('type', 30)->default('string');
            $table->string('group', 60)->default('general')->index();
            $table->text('description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('system_configurations');
        Schema::dropIfExists('master_list_items');
        Schema::dropIfExists('master_lists');
        Schema::dropIfExists('audit_focuses');
        Schema::dropIfExists('audit_area_office');
        Schema::dropIfExists('audit_areas');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'employee_id',
                'contact_number',
                'birth_date',
                'is_office_head',
            ]);
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['sector', 'contact_number']);
        });
    }
};
