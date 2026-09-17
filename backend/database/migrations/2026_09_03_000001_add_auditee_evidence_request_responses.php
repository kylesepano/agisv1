<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aems_evidence_request_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('evidence_request_id')->constrained('aems_evidence_requests')->cascadeOnDelete();
            $table->foreignId('audit_evidence_id')->constrained('audit_evidence')->restrictOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->useCurrent();
            $table->text('response_note')->nullable();
            $table->timestamps();
            $table->unique(
                ['evidence_request_id', 'audit_evidence_id', 'document_version_id'],
                'aems_evidence_request_response_unique',
            );
            $table->index(['evidence_request_id', 'submitted_at'], 'aems_evidence_request_response_idx');
        });

        $now = now();
        $permissionId = DB::table('permissions')->where('code', 'aems.evidence-request.respond')->value('id');
        if (! $permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'code' => 'aems.evidence-request.respond',
                'name' => 'Respond Aems Evidence Request',
                'module' => 'aems.evidence-request',
                'action' => 'respond',
                'description' => 'Allows an auditee to upload evidence in response to a sent AEMS Evidence Request.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $scopePermissionId = DB::table('permissions')->where('code', 'access.auditee_scope')->value('id');
        $auditeeRoleId = DB::table('roles')->where('code', 'auditee_representative')->value('id');
        if ($auditeeRoleId && $scopePermissionId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $auditeeRoleId,
                'permission_id' => $scopePermissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($auditeeRoleId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $auditeeRoleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aems_evidence_request_responses');
    }
};
