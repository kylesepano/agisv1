<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates Audit Universe subjects, stakeholders, and subject-history records. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iap_audit_universe_items', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_code', 60)->unique();
            $table->string('name');
            $table->foreignId('subject_type_id')->constrained('master_list_items')->restrictOnDelete();
            $table->foreignId('responsible_office_id')->constrained('offices')->restrictOnDelete();
            $table->foreignId('primary_audit_area_id')->constrained('audit_areas')->restrictOnDelete();
            $table->foreignId('materiality_level_id')->nullable()->constrained('master_list_items')->restrictOnDelete();
            $table->text('description');
            $table->text('audit_scope')->nullable();
            $table->text('materiality_exposure')->nullable();
            $table->date('last_audit_date')->nullable()->index();
            $table->text('historical_audit_summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['responsible_office_id', 'primary_audit_area_id'],
                'iap_universe_office_area_index',
            );
            $table->index(
                ['materiality_level_id', 'is_active'],
                'iap_universe_materiality_index',
            );
        });

        Schema::create('iap_audit_universe_stakeholders', function (Blueprint $table): void {
            $table->foreignId('audit_universe_item_id')
                ->constrained('iap_audit_universe_items')
                ->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices')->restrictOnDelete();
            $table->timestamps();

            $table->primary(
                ['audit_universe_item_id', 'office_id'],
                'iap_universe_stakeholder_primary',
            );
        });

        Schema::create('iap_audit_universe_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_universe_item_id')
                ->constrained('iap_audit_universe_items')
                ->cascadeOnDelete();
            $table->date('audited_on')->index();
            $table->string('engagement_reference', 100)->nullable();
            $table->string('title');
            $table->string('outcome', 120)->nullable();
            $table->string('report_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['audit_universe_item_id', 'audited_on'],
                'iap_universe_history_item_date_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_audit_universe_history');
        Schema::dropIfExists('iap_audit_universe_stakeholders');
        Schema::dropIfExists('iap_audit_universe_items');
    }
};
