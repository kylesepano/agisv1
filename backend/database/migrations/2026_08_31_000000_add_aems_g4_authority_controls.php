<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds auditable AEO authority, distribution, and controlled team-amendment records. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_engagement_orders', function (Blueprint $table): void {
            $table->foreignId('cancelled_by')->nullable()->after('issued_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->foreignId('voided_by')->nullable()->after('cancelled_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->unsignedInteger('amended_from_version_number')->nullable()->after('voided_at');
            $table->foreignId('superseded_by_order_id')->nullable()->after('amended_from_version_number')->constrained('audit_engagement_orders')->nullOnDelete();
        });

        Schema::create('aems_aeo_signatories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_order_id')->constrained('audit_engagement_orders')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedInteger('sequence')->default(1);
            $table->string('signatory_role', 40);
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_required')->default(true);
            $table->string('status', 20)->default('PENDING');
            $table->string('signature_method', 40)->nullable();
            $table->string('signature_reference', 160)->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['audit_engagement_order_id', 'version_number', 'signatory_role'], 'aems_aeo_signatory_role_unique');
            $table->index(['audit_engagement_order_id', 'version_number', 'status'], 'aems_aeo_signatory_status_idx');
        });

        Schema::create('aems_aeo_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_order_id')->constrained('audit_engagement_orders')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('recipient_type', 20);
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('recipient_office_id')->nullable()->constrained('offices')->restrictOnDelete();
            $table->string('recipient_name', 180)->nullable();
            $table->string('transmittal_method', 40);
            $table->string('transmittal_reference', 160)->nullable();
            $table->string('status', 24)->default('PENDING');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('acknowledgement_note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['audit_engagement_order_id', 'version_number', 'status'], 'aems_aeo_distribution_status_idx');
        });

        Schema::create('aems_team_amendments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->foreignId('engagement_team_id')->nullable()->constrained('engagement_teams')->nullOnDelete();
            $table->string('action', 40);
            $table->string('authority_code', 80);
            $table->text('reason');
            $table->text('consequence_assessment');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('authority_document_version_id')->nullable()->constrained('document_versions')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['audit_engagement_id', 'action'], 'aems_team_amendment_action_idx');
        });

        Schema::create('aems_team_access_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_engagement_id')->constrained('audit_engagements')->cascadeOnDelete();
            $table->foreignId('engagement_team_id')->nullable()->constrained('engagement_teams')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->string('assignment_role_code', 40);
            $table->date('access_from')->nullable();
            $table->date('access_until')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['audit_engagement_id', 'user_id', 'created_at'], 'aems_team_access_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_team_access_history');
        Schema::dropIfExists('aems_team_amendments');
        Schema::dropIfExists('aems_aeo_distributions');
        Schema::dropIfExists('aems_aeo_signatories');

        Schema::table('audit_engagement_orders', function (Blueprint $table): void {
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['superseded_by_order_id']);
            $table->dropColumn([
                'cancelled_by', 'cancelled_at', 'voided_by', 'voided_at',
                'amended_from_version_number', 'superseded_by_order_id',
            ]);
        });
    }
};
