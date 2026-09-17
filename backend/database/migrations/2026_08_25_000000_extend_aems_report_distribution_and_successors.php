<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_reports', function (Blueprint $table): void {
            $table->foreignId('supersedes_report_id')
                ->nullable()
                ->after('current_version_id')
                ->constrained('audit_reports')
                ->restrictOnDelete();
            $table->timestamp('withdrawn_at')->nullable()->after('issued_at');
            $table->foreignId('withdrawn_by')->nullable()->after('withdrawn_at')->constrained('users')->restrictOnDelete();
            $table->text('withdrawal_reason')->nullable()->after('withdrawn_by');
        });

        Schema::table('report_recipients', function (Blueprint $table): void {
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
        });

        Schema::create('audit_report_distribution_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_report_id')->constrained('audit_reports')->restrictOnDelete();
            $table->foreignId('audit_report_version_id')->constrained('audit_report_versions')->restrictOnDelete();
            $table->foreignId('report_recipient_id')->constrained('report_recipients')->restrictOnDelete();
            $table->string('decision_code', 40);
            $table->text('comment')->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->useCurrent();
            $table->timestamps();
            $table->index(['audit_report_version_id', 'report_recipient_id'], 'aem_report_distribution_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_report_distribution_decisions');
        Schema::table('report_recipients', function (Blueprint $table): void {
            $table->dropColumn('delivered_at');
        });
        Schema::table('audit_reports', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('withdrawn_by');
            $table->dropConstrainedForeignId('supersedes_report_id');
            $table->dropColumn(['withdrawn_at', 'withdrawal_reason']);
        });
    }
};
