<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds the controlled G6 issue, AFR transmittal, and response extension contract. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_issues', function (Blueprint $table): void {
            $table->timestamp('withdrawn_at')->nullable()->after('converted_at');
            $table->foreignId('withdrawn_by')->nullable()->after('withdrawn_at')
                ->constrained('users')->restrictOnDelete();
            $table->text('withdrawal_reason')->nullable()->after('withdrawn_by');
            $table->index(['audit_engagement_id', 'status', 'disposition'], 'aem_issue_g6_terminal_idx');
        });

        Schema::table('management_responses', function (Blueprint $table): void {
            $table->string('response_kind', 30)->default('ORIGINAL')->after('response_code');
            $table->timestamp('extension_requested_at')->nullable()->after('clarification_request');
            $table->foreignId('extension_requested_by')->nullable()->after('extension_requested_at')
                ->constrained('users')->restrictOnDelete();
            $table->date('extension_requested_due_date')->nullable()->after('extension_requested_by');
            $table->timestamp('extension_approved_at')->nullable()->after('extension_requested_due_date');
            $table->foreignId('extension_approved_by')->nullable()->after('extension_approved_at')
                ->constrained('users')->restrictOnDelete();
            $table->date('extension_approved_due_date')->nullable()->after('extension_approved_by');
            $table->text('extension_reason')->nullable()->after('extension_approved_due_date');
            $table->boolean('submitted_late')->default(false)->after('extension_reason');
            $table->text('late_reason')->nullable()->after('submitted_late');
            $table->text('supplemental_reason')->nullable()->after('late_reason');
            $table->index(['audit_finding_id', 'response_kind', 'is_current_revision'], 'aem_response_g6_kind_idx');
        });

        Schema::create('aems_finding_transmittals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->foreignId('audit_finding_id')->constrained('audit_findings')->restrictOnDelete();
            $table->string('transmittal_code', 80)->unique();
            $table->unsignedInteger('finding_revision_number');
            $table->string('transmittal_method', 40);
            $table->string('transmittal_reference', 255)->nullable();
            $table->string('confidentiality', 30)->default('INTERNAL');
            $table->foreignId('sent_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('sent_at');
            $table->date('response_due_date')->nullable();
            $table->json('content_snapshot');
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['audit_engagement_id', 'audit_finding_id']);
        });

        Schema::create('aems_finding_transmittal_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transmittal_id')->constrained('aems_finding_transmittals')->cascadeOnDelete();
            $table->string('recipient_type', 20)->default('OFFICE');
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('recipient_name', 255);
            $table->string('delivery_status', 30)->default('PENDING');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('acknowledgement_comment')->nullable();
            $table->string('delivery_reference', 255)->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->index(['transmittal_id', 'delivery_status']);
        });

        Schema::create('aems_finding_transmittal_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transmittal_id')->constrained('aems_finding_transmittals')->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('aems_finding_transmittal_recipients')->nullOnDelete();
            $table->string('event_type', 40);
            $table->text('content')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->index(['transmittal_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_finding_transmittal_events');
        Schema::dropIfExists('aems_finding_transmittal_recipients');
        Schema::dropIfExists('aems_finding_transmittals');

        Schema::table('management_responses', function (Blueprint $table): void {
            $table->dropIndex('aem_response_g6_kind_idx');
            $table->dropConstrainedForeignId('extension_requested_by');
            $table->dropConstrainedForeignId('extension_approved_by');
            $table->dropColumn([
                'response_kind', 'extension_requested_at', 'extension_requested_due_date',
                'extension_approved_at', 'extension_approved_due_date', 'extension_reason',
                'submitted_late', 'late_reason', 'supplemental_reason',
            ]);
        });

        Schema::table('audit_issues', function (Blueprint $table): void {
            $table->dropIndex('aem_issue_g6_terminal_idx');
            $table->dropConstrainedForeignId('withdrawn_by');
            $table->dropColumn(['withdrawn_at', 'withdrawal_reason']);
        });
    }
};
