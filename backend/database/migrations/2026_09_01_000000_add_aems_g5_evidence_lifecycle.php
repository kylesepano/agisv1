<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aems_evidence_requests', function (Blueprint $table): void {
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgement_note')->nullable();
            $table->date('extension_requested_due_date')->nullable();
            $table->foreignId('extension_requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('extension_requested_at')->nullable();
            $table->date('extension_due_date')->nullable();
            $table->foreignId('extension_approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('extension_approved_at')->nullable();
            $table->text('extension_reason')->nullable();
            $table->timestamp('overdue_at')->nullable();
            $table->foreignId('escalated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->text('escalation_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('closure_type', 40)->nullable();
        });

        Schema::create('aems_evidence_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidence_request_id')->constrained('aems_evidence_requests')->cascadeOnDelete();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->string('event_type', 60);
            $table->string('from_status', 50)->nullable();
            $table->string('to_status', 50)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['evidence_request_id', 'created_at']);
        });

        Schema::table('aems_evidence_request_evidence', function (Blueprint $table): void {
            $table->string('receipt_status', 30)->default('RECEIVED');
            $table->string('receipt_outcome', 40)->nullable();
            $table->string('received_form', 40)->nullable();
            $table->string('acquisition_method', 50)->nullable();
        });

        Schema::table('audit_evidence', function (Blueprint $table): void {
            $table->string('outcome', 40)->default('REGISTERED')->index();
            $table->string('acquisition_method', 50)->nullable();
            $table->string('acquisition_form', 40)->nullable();
            $table->foreignId('planning_objective_id')->nullable()->constrained('aems_planning_objectives')->restrictOnDelete();
            $table->foreignId('risk_matrix_item_id')->nullable()->constrained('aems_risk_matrix_items')->restrictOnDelete();
            $table->string('control_reference', 160)->nullable();
        });
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS aem_current_evidence_unique');
            DB::statement('CREATE UNIQUE INDEX aem_current_evidence_unique ON audit_evidence (audit_engagement_id, evidence_code) WHERE is_current_revision = true AND deleted_at IS NULL');
        }

        Schema::create('aems_evidence_report_links', function (Blueprint $table): void {
            $table->foreignId('audit_evidence_id')->constrained('audit_evidence')->cascadeOnDelete();
            $table->foreignId('audit_report_version_id')->constrained('audit_report_versions')->restrictOnDelete();
            $table->unsignedInteger('sequence_number')->default(0);
            $table->string('link_reason', 500)->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['audit_evidence_id', 'audit_report_version_id'], 'aems_evidence_report_link_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_evidence_report_links');
        Schema::table('audit_evidence', function (Blueprint $table): void {
            $table->dropForeign(['planning_objective_id']);
            $table->dropForeign(['risk_matrix_item_id']);
            $table->dropColumn(['outcome', 'acquisition_method', 'acquisition_form', 'planning_objective_id', 'risk_matrix_item_id', 'control_reference']);
        });
        Schema::table('aems_evidence_request_evidence', function (Blueprint $table): void {
            $table->dropColumn(['receipt_status', 'receipt_outcome', 'received_form', 'acquisition_method']);
        });
        Schema::dropIfExists('aems_evidence_request_events');
        Schema::table('aems_evidence_requests', function (Blueprint $table): void {
            $table->dropForeign(['acknowledged_by']);
            $table->dropForeign(['extension_requested_by']);
            $table->dropForeign(['extension_approved_by']);
            $table->dropForeign(['escalated_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropColumn([
                'acknowledged_by', 'acknowledged_at', 'acknowledgement_note',
                'extension_requested_due_date', 'extension_requested_by', 'extension_requested_at',
                'extension_due_date', 'extension_approved_by', 'extension_approved_at', 'extension_reason',
                'overdue_at', 'escalated_by', 'escalated_at', 'escalation_reason',
                'cancelled_by', 'cancelled_at', 'cancellation_reason', 'closure_type',
            ]);
        });
    }
};
