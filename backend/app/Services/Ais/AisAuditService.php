<?php

namespace App\Services\Ais;

use App\Models\AuditLog;
use App\Support\ActivityRecorder;
use Illuminate\Http\Request;

/** Records AIS reads and security-relevant operations in both audit layers. */
class AisAuditService
{
    /** @param array<string, mixed> $metadata */
    public function view(Request $request, string $action, string $description, array $metadata = []): void
    {
        $metadata = ['module' => 'AIS', ...$metadata];
        ActivityRecorder::record($request, $action, $description, metadata: $metadata);
        AuditLog::query()->create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'auditable_type' => AisAuditService::class,
            'auditable_id' => null,
            'new_values' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }
}
