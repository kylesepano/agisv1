<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $coverageRows = DB::table('audit_engagement_audit_areas')
            ->select(['audit_engagement_id', 'audit_area_id', 'coverage_metadata'])
            ->get();
        $desiredByEngagement = [];
        $completeByEngagement = [];

        foreach ($coverageRows as $coverageRow) {
            $metadata = is_string($coverageRow->coverage_metadata)
                ? json_decode($coverageRow->coverage_metadata, true)
                : $coverageRow->coverage_metadata;

            if (! is_array($metadata) || ! array_key_exists('focusIds', $metadata)) {
                $completeByEngagement[$coverageRow->audit_engagement_id] = false;
                continue;
            }

            $completeByEngagement[$coverageRow->audit_engagement_id] ??= true;
            $focusIds = collect($metadata['focusIds'] ?? [])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values();
            $validFocusIds = DB::table('audit_focuses')
                ->whereIn('id', $focusIds)
                ->where('audit_area_id', $coverageRow->audit_area_id)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id);

            foreach ($validFocusIds as $focusId) {
                $desiredByEngagement[$coverageRow->audit_engagement_id][$focusId] = [
                    'audit_engagement_id' => $coverageRow->audit_engagement_id,
                    'audit_focus_id' => $focusId,
                    'coverage_metadata' => json_encode([
                        'auditAreaId' => $coverageRow->audit_area_id,
                        'boundary' => $metadata['boundary'] ?? null,
                        'limitations' => $metadata['limitations'] ?? null,
                        'sourceVariance' => $metadata['sourceVariance'] ?? null,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => now(),
                        'updated_at' => now(),
                ];
            }
        }

        foreach ($completeByEngagement as $engagementId => $complete) {
            if (! $complete) {
                continue;
            }

            $desired = collect($desiredByEngagement[$engagementId] ?? [])
                ->keyBy('audit_focus_id');
            $currentIds = DB::table('audit_engagement_audit_focuses')
                ->where('audit_engagement_id', $engagementId)
                ->pluck('audit_focus_id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();
            $desiredIds = $desired->keys()->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($currentIds === $desiredIds) {
                continue;
            }

            DB::table('audit_engagement_audit_focuses')
                ->where('audit_engagement_id', $engagementId)
                ->delete();
            if ($desired->isNotEmpty()) {
                DB::table('audit_engagement_audit_focuses')->insert($desired->values()->all());
            }
        }
    }

    public function down(): void
    {
        // The area coverage metadata remains the authoritative source needed
        // to reconstruct these pivots; the reconciliation is intentionally not
        // reversed into the previously inconsistent state.
    }
};
