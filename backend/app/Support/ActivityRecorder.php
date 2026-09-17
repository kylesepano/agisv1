<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Centralizes operational and before/after audit logging for business actions.
 */
class ActivityRecorder
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public static function record(
        Request $request,
        string $action,
        string $description,
        ?User $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): ActivityLog {
        $activity = ActivityLog::query()->create([
            'user_id' => $request->user()?->id,
            'subject_user_id' => $subject?->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);

        // User administration and profile changes belong in both layers:
        // Activity Log explains the operation; Audit Trail preserves field deltas.
        if ($subject && ($oldValues !== null || $newValues !== null)) {
            AuditLog::query()->create([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'auditable_type' => User::class,
                'auditable_id' => $subject->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                'metadata' => $metadata,
            ]);
        }

        return $activity;
    }
}
