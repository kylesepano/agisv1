<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds schedule dates, proposed teams, skill requirements, and schedule history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('iap_plan_engagements', function (Blueprint $table): void {
            $table->date('expected_report_date')->nullable()->after('planned_end_date');
            $table->string('schedule_status', 30)
                ->default('UNSCHEDULED')
                ->after('expected_report_date')
                ->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('last_rescheduled_at')->nullable();
            $table->foreignId('last_rescheduled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('last_reschedule_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancellation_reason')->nullable();
        });

        Schema::create('iap_auditor_capacities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->decimal('available_person_days', 8, 2)->default(180);
            $table->text('notes')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['fiscal_year', 'user_id'], 'iap_auditor_capacity_year_user_unique');
        });

        Schema::create('iap_schedule_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_engagement_id')
                ->constrained('iap_plan_engagements')
                ->restrictOnDelete();
            $table->string('action', 30);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->date('old_start_date')->nullable();
            $table->date('old_end_date')->nullable();
            $table->date('old_expected_report_date')->nullable();
            $table->date('new_start_date')->nullable();
            $table->date('new_end_date')->nullable();
            $table->date('new_expected_report_date')->nullable();
            $table->json('old_team')->nullable();
            $table->json('new_team')->nullable();
            $table->json('conflicts')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['plan_engagement_id', 'created_at'], 'iap_schedule_event_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iap_schedule_events');
        Schema::dropIfExists('iap_auditor_capacities');

        Schema::table('iap_plan_engagements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('last_rescheduled_by');
            $table->dropConstrainedForeignId('scheduled_by');
            $table->dropColumn([
                'expected_report_date',
                'schedule_status',
                'scheduled_at',
                'last_rescheduled_at',
                'last_reschedule_reason',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
