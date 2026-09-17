<?php

namespace App\Http\Controllers\Api\Core;

use App\Http\Controllers\Controller;
use App\Support\ActivityRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/**
 * Records authorized detail-page views without making telemetry a navigation blocker.
 */
class RecordViewController extends Controller
{
    /**
     * The browser may report only detail views the current user could have
     * opened through the corresponding protected module.
     */
    private const PERMISSIONS = [
        'OFFICE' => 'offices.view',
        'AUDIT_AREA' => 'audit_areas.view',
        'AUDIT_FOCUS' => 'audit_focus.view',
        'USER' => 'users.view',
        'ACCESS_ROLE' => 'roles.view',
        'MASTER_LIST' => 'master_lists.view',
        'DOCUMENT' => 'documents.view',
        'WORKFLOW_DEFINITION' => 'workflows.view',
        'WORKFLOW_INSTANCE' => 'workflows.monitor',
        'NOTIFICATION' => 'notifications.view',
        'SIAP' => 'iap.view',
        'IAP_PLAN' => 'iap.view',
        'IAP_RISK' => 'iap.view',
        'AUDIT_UNIVERSE' => 'iap.view',
        'RISK_PERIOD' => 'iap.view',
        'PRIORITIZATION' => 'iap.view',
        'SCHEDULE' => 'iap.view',
        'IAP_REPORT' => 'iap.view',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'module' => ['required', 'string', Rule::in(['CORE', 'IAP'])],
            'recordType' => ['required', 'string', Rule::in(array_keys(self::PERMISSIONS))],
            'recordId' => ['required', 'integer', 'min:1'],
            'recordCode' => ['nullable', 'string', 'max:150'],
            'recordLabel' => ['required', 'string', 'max:255'],
            'route' => ['nullable', 'string', 'max:500'],
        ]);
        abort_unless(
            $request->user()->hasPermission(self::PERMISSIONS[$validated['recordType']]),
            403,
        );

        // React rerenders and repeated modal opens should not flood the activity
        // log, while a later intentional revisit should remain observable.
        $dedupeKey = implode(':', [
            'agis',
            'record-view',
            $request->user()->id,
            $validated['recordType'],
            $validated['recordId'],
        ]);
        $recorded = Cache::add($dedupeKey, true, now()->addMinutes(5));
        if ($recorded) {
            $code = $validated['recordCode'] ?? "#{$validated['recordId']}";
            ActivityRecorder::record(
                $request,
                strtolower($validated['module']).'.'.strtolower($validated['recordType']).'.viewed',
                "Viewed {$code} — {$validated['recordLabel']}.",
                metadata: [
                    'module' => $validated['module'],
                    'recordType' => $validated['recordType'],
                    'recordId' => $validated['recordId'],
                    'recordCode' => $validated['recordCode'] ?? null,
                    'route' => $validated['route'] ?? null,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'data' => ['recorded' => $recorded],
        ]);
    }
}
