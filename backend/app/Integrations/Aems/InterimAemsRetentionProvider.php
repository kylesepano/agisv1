<?php

namespace App\Integrations\Aems;

use App\Contracts\Aems\EngagementRetentionProvider;
use App\Models\AuditEngagement;
use App\Models\EngagementClosure;
use App\Models\EngagementRetentionRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InterimAemsRetentionProvider implements EngagementRetentionProvider
{
    public function save(
        AuditEngagement $engagement,
        ?EngagementClosure $closure,
        User $actor,
        array $attributes,
    ): EngagementRetentionRecord {
        $existing = EngagementRetentionRecord::query()
            ->where('audit_engagement_id', $engagement->id)
            ->first();
        if ($existing?->approved_at) {
            throw ValidationException::withMessages([
                'retention' => ['Approved retention metadata is immutable.'],
            ]);
        }
        $permanent = (bool) ($attributes['permanentFlag'] ?? false);
        if ($permanent && filled($attributes['scheduledDispositionDate'] ?? null)) {
            throw ValidationException::withMessages([
                'scheduledDispositionDate' => ['Permanent records cannot have a disposition date.'],
            ]);
        }
        if (! $permanent && empty($attributes['retentionPeriodValue'])) {
            throw ValidationException::withMessages([
                'retentionPeriodValue' => ['A retention period is required unless the record is permanent.'],
            ]);
        }
        $start = Carbon::parse($attributes['retentionStartDate']);
        $disposition = null;
        if (! $permanent) {
            $value = (int) $attributes['retentionPeriodValue'];
            $unit = strtoupper($attributes['retentionPeriodUnit']);
            $disposition = match ($unit) {
                'DAYS' => $start->copy()->addDays($value),
                'MONTHS' => $start->copy()->addMonths($value),
                'YEARS' => $start->copy()->addYears($value),
                default => throw ValidationException::withMessages([
                    'retentionPeriodUnit' => ['Retention unit must be DAYS, MONTHS, or YEARS.'],
                ]),
            };
        }

        return EngagementRetentionRecord::query()->updateOrCreate(
            ['audit_engagement_id' => $engagement->id],
            [
                'engagement_closure_id' => $closure?->id,
                'retention_classification_code' => strtoupper($attributes['retentionClassificationCode']),
                'retention_trigger_code' => strtoupper($attributes['retentionTriggerCode']),
                'retention_start_date' => $start->toDateString(),
                'retention_period_value' => $permanent ? null : $attributes['retentionPeriodValue'],
                'retention_period_unit' => $permanent ? null : strtoupper($attributes['retentionPeriodUnit']),
                'permanent_flag' => $permanent,
                'scheduled_disposition_date' => $disposition?->toDateString(),
                'custodian_user_id' => $attributes['custodianUserId'] ?? null,
                'custodian_office_id' => $attributes['custodianOfficeId'],
                'storage_location_description' => $attributes['storageLocationDescription'] ?? null,
                'legal_hold_flag' => (bool) ($attributes['legalHoldFlag'] ?? false),
                'legal_hold_reference' => $attributes['legalHoldReference'] ?? null,
                'lock_version' => ($existing?->lock_version ?? 0) + 1,
            ],
        );
    }

    public function approve(
        EngagementRetentionRecord $record,
        User $actor,
        int $lockVersion,
    ): EngagementRetentionRecord {
        return DB::transaction(function () use ($record, $actor, $lockVersion): EngagementRetentionRecord {
            $locked = EngagementRetentionRecord::query()->lockForUpdate()->findOrFail($record->id);
            if ($locked->lock_version !== $lockVersion) {
                throw ValidationException::withMessages([
                    'lockVersion' => ['Retention metadata changed in another session. Refresh first.'],
                ]);
            }
            $readiness = $this->readiness($locked);
            $metadataBlockers = array_values(array_filter(
                $readiness['blockers'],
                fn (string $blocker): bool => $blocker !== 'Retention metadata requires CIAS Management approval.',
            ));
            if ($metadataBlockers !== []) {
                throw ValidationException::withMessages(['retention' => $metadataBlockers]);
            }
            $snapshot = $locked->toArray();
            $locked->forceFill([
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'approved_snapshot_json' => $snapshot,
                'lock_version' => $locked->lock_version + 1,
            ])->save();

            return $locked->fresh();
        });
    }

    public function readiness(?EngagementRetentionRecord $record): array
    {
        if (! $record) {
            return ['ready' => false, 'blockers' => ['Retention metadata has not been created.']];
        }
        $blockers = [];
        if (! $record->custodian_user_id || ! $record->custodian_office_id) {
            $blockers[] = 'A record custodian and custodian office are required.';
        }
        if (! $record->retention_classification_code || ! $record->retention_trigger_code) {
            $blockers[] = 'Retention classification and trigger are required.';
        }
        if (! $record->permanent_flag && ! $record->scheduled_disposition_date) {
            $blockers[] = 'A retention period and calculated disposition date are required.';
        }
        if ($record->permanent_flag && $record->scheduled_disposition_date) {
            $blockers[] = 'Permanent records cannot have a disposition date.';
        }
        if ($record->legal_hold_flag && ! $record->legal_hold_reference) {
            $blockers[] = 'A legal-hold reference is required while legal hold is active.';
        }
        if (! $record->approved_at) {
            $blockers[] = 'Retention metadata requires CIAS Management approval.';
        }

        return ['ready' => $blockers === [], 'blockers' => $blockers];
    }
}
