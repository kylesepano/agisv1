<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditLog;
use App\Models\EngagementEvent;
use App\Support\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes the operational activity, field-level audit trail, and engagement event
 * records required for every AEMS registry change.
 */
class AemsSupport
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function audit(
        Request $request,
        string $action,
        AuditEngagement $engagement,
        ?array $oldValues,
        ?array $newValues,
        ?array $metadata = null,
    ): void {
        AuditLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => AuditEngagement::class,
            'auditable_id' => $engagement->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => ['module' => 'AEMS', ...($metadata ?? [])],
        ]);

        ActivityRecorder::record(
            $request,
            $action,
            Str::headline(strtolower(str_replace('.', ' ', $action))).': '.$engagement->engagement_code,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: [
                'module' => 'AEMS',
                'recordType' => AuditEngagement::class,
                'recordId' => $engagement->id,
                'path' => "/audit-engagement-management/{$engagement->id}",
                ...($metadata ?? []),
            ],
        );
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function event(
        Request $request,
        AuditEngagement $engagement,
        string $action,
        ?string $fromStatus,
        ?string $toStatus,
        ?array $oldValues,
        ?array $newValues,
        ?string $comment = null,
        string $subjectType = 'ENGAGEMENT',
        ?int $subjectId = null,
        ?int $subjectVersion = null,
        ?string $subjectCode = null,
        ?string $subjectFamilyUuid = null,
        ?array $documentVersionIds = null,
    ): EngagementEvent {
        $role = $request->user()->effectiveRoles()->first();

        return EngagementEvent::query()->create([
            'audit_engagement_id' => $engagement->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId ?? $engagement->id,
            'subject_family_uuid' => $subjectFamilyUuid,
            'subject_version' => $subjectVersion,
            'subject_code' => $subjectCode ?? $engagement->engagement_code,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'actor_id' => $request->user()->id,
            'actor_role_code' => $role?->code,
            'office_id' => $request->user()->office_id,
            'comment' => $comment,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'record_lock_version' => $engagement->lock_version,
            'document_version_ids' => $documentVersionIds,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
        ]);
    }
}
