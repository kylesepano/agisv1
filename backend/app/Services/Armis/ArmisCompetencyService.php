<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\ArmisCompetency;
use App\Models\ArmisResourceProfile;
use App\Models\ArmisWorkflowEvent;
use App\Models\AuditLog;
use App\Models\DocumentVersion;
use App\Models\MasterListItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Governs ARMIS competency claims, certification evidence, review, and revisions. */
class ArmisCompetencyService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** @return Builder<ArmisCompetency> */
    public function scopeVisible(Builder $query, User $actor): Builder
    {
        return $query->whereHas('resourceProfile', function (Builder $profileQuery) use ($actor): void {
            if (! $actor->hasGlobalOfficeAccess()) {
                $profileQuery->where('office_id', $actor->office_id);
            }
        });
    }

    public function resolveVisible(User $actor, int $id, bool $withTrashed = false): ArmisCompetency
    {
        $query = $withTrashed ? ArmisCompetency::withTrashed() : ArmisCompetency::query();
        $competency = $this->scopeVisible($query, $actor)
            ->with($this->relations())
            ->find($id);

        abort_unless($competency, 404, 'The ARMIS competency was not found in your scope.');

        return $competency;
    }

    /** @param array<string, mixed> $attributes */
    public function create(Request $request, array $attributes): ArmisCompetency
    {
        $actor = $this->actor($request);
        $profile = $this->profile($actor, (int) $attributes['resource_profile_id']);
        $catalog = $this->catalogItem((int) $attributes['competency_id']);
        $evidence = $this->evidence($attributes['evidence_document_version_id'] ?? null);

        return DB::transaction(function () use ($request, $actor, $profile, $catalog, $evidence, $attributes): ArmisCompetency {
            $duplicate = ArmisCompetency::query()
                ->where('resource_profile_id', $profile->id)
                ->where('competency_id', $catalog->id)
                ->where('is_current_revision', true)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'competencyId' => ['A current competency claim already exists for this resource. Create a revision instead.'],
                ]);
            }

            $record = ArmisCompetency::query()->create([
                'competency_family_uuid' => (string) Str::uuid(),
                'resource_profile_id' => $profile->id,
                'competency_id' => $catalog->id,
                'version_number' => 1,
                'is_current_revision' => true,
                'proficiency_level' => $attributes['proficiency_level'] ?? 'INTERMEDIATE',
                'credential_type' => $attributes['credential_type'] ?? null,
                'credential_reference' => $attributes['credential_reference'] ?? null,
                'issuer' => $attributes['issuer'] ?? null,
                'issued_at' => $attributes['issued_at'] ?? null,
                'status' => 'DRAFT',
                'evidence_document_version_id' => $evidence?->id,
                'expires_at' => $attributes['expires_at'] ?? null,
                'notes' => $attributes['notes'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $this->event($record, 'COMPETENCY_CREATED', null, 'DRAFT', $actor, null, [
                'competencyCode' => $catalog->code,
                'versionNumber' => 1,
            ]);
            $this->record($request, 'armis.competency.created', 'ARMIS competency claim created.', $record, null, $record->toArray());

            return $record->fresh($this->relations());
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function update(Request $request, ArmisCompetency $competency, array $attributes): ArmisCompetency
    {
        $actor = $this->actor($request);
        $competency->loadMissing('resourceProfile');
        $this->assertOfficeScope($actor, $competency->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $competency, $attributes): ArmisCompetency {
            $locked = ArmisCompetency::query()->lockForUpdate()->findOrFail($competency->id);
            $this->assertLock($locked, (int) $attributes['lock_version']);
            abort_if($locked->trashed() || ! $locked->is_current_revision, 409, 'Only the current competency revision can be edited.');
            abort_unless(in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Submitted or verified competency records are immutable. Create a revision for corrections.');

            $catalog = $this->catalogItem((int) ($attributes['competency_id'] ?? $locked->competency_id));
            $evidence = $this->evidence(
                array_key_exists('evidence_document_version_id', $attributes)
                    ? $attributes['evidence_document_version_id']
                    : $locked->evidence_document_version_id,
            );
            $before = $locked->toArray();
            $locked->fill([
                'competency_id' => $catalog->id,
                'proficiency_level' => $attributes['proficiency_level'] ?? $locked->proficiency_level,
                'credential_type' => array_key_exists('credential_type', $attributes) ? $attributes['credential_type'] : $locked->credential_type,
                'credential_reference' => array_key_exists('credential_reference', $attributes) ? $attributes['credential_reference'] : $locked->credential_reference,
                'issuer' => array_key_exists('issuer', $attributes) ? $attributes['issuer'] : $locked->issuer,
                'issued_at' => array_key_exists('issued_at', $attributes) ? $attributes['issued_at'] : $locked->issued_at,
                'evidence_document_version_id' => $evidence?->id,
                'expires_at' => array_key_exists('expires_at', $attributes) ? $attributes['expires_at'] : $locked->expires_at,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $locked->notes,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $locked->save();
            $this->event($locked, 'COMPETENCY_UPDATED', $before['status'] ?? null, $locked->status, $actor, null, ['lockVersion' => $locked->lock_version]);
            $this->record($request, 'armis.competency.updated', 'ARMIS competency claim updated.', $locked, $before, $locked->fresh()->toArray());

            return $locked->fresh($this->relations());
        }, 3);
    }

    public function submit(Request $request, ArmisCompetency $competency, int $lockVersion): ArmisCompetency
    {
        $actor = $this->actor($request);
        $competency->loadMissing('resourceProfile');
        $this->assertOfficeScope($actor, $competency->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $competency, $lockVersion): ArmisCompetency {
            $locked = ArmisCompetency::query()->lockForUpdate()->findOrFail($competency->id);
            $this->assertLock($locked, $lockVersion);
            abort_unless($locked->is_current_revision && in_array($locked->status, ['DRAFT', 'RETURNED'], true), 409, 'Only a current draft competency can be submitted.');
            $profile = $locked->resourceProfile()->firstOrFail();
            abort_if($profile->status !== 'ACTIVE', 409, 'The linked ARMIS resource must be active before competency verification.');
            $this->evidence($locked->evidence_document_version_id);

            $locked->update([
                'status' => 'PENDING_VERIFICATION',
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'verification_notes' => null,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->event($locked, 'COMPETENCY_SUBMITTED', 'DRAFT', 'PENDING_VERIFICATION', $actor);
            $this->record($request, 'armis.competency.submitted', 'ARMIS competency submitted for independent verification.', $locked, ['status' => 'DRAFT'], ['status' => 'PENDING_VERIFICATION']);
            $this->notifyReviewers($locked, $actor, 'submitted');

            return $locked->fresh($this->relations());
        }, 3);
    }

    public function review(Request $request, ArmisCompetency $competency, string $decision, int $lockVersion, ?string $notes = null): ArmisCompetency
    {
        $actor = $this->actor($request);
        $competency->loadMissing('resourceProfile');
        $this->assertOfficeScope($actor, $competency->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $competency, $decision, $lockVersion, $notes): ArmisCompetency {
            $locked = ArmisCompetency::query()->lockForUpdate()->findOrFail($competency->id);
            $this->assertLock($locked, $lockVersion);
            $profile = $locked->resourceProfile()->firstOrFail();
            $this->assertIndependent($actor, $locked, $profile);
            abort_if($locked->trashed() || ! $locked->is_current_revision, 409, 'Only the current competency revision can be reviewed.');

            $from = $locked->status;
            if ($decision === 'VERIFY') {
                abort_unless($from === 'PENDING_VERIFICATION', 409, 'Only pending competency claims can be verified.');
                $this->evidence($locked->evidence_document_version_id);
                $to = 'VERIFIED';
                $event = 'COMPETENCY_VERIFIED';
            } elseif ($decision === 'RETURN') {
                abort_unless($from === 'PENDING_VERIFICATION', 409, 'Only pending competency claims can be returned.');
                abort_if(blank($notes), 422, 'A return explanation is required.');
                $to = 'RETURNED';
                $event = 'COMPETENCY_RETURNED';
            } elseif ($decision === 'REVOKE') {
                abort_unless(in_array($from, ['VERIFIED', 'EXPIRED'], true), 409, 'Only verified or expired competency claims can be revoked.');
                abort_if(blank($notes), 422, 'A revocation explanation is required.');
                $to = 'REVOKED';
                $event = 'COMPETENCY_REVOKED';
            } else {
                throw ValidationException::withMessages(['decision' => ['Unsupported competency review decision.']]);
            }

            $values = [
                'status' => $to,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'verification_notes' => $notes,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ];
            if ($decision === 'VERIFY') {
                $values['verified_by'] = $actor->id;
                $values['verified_at'] = now();
            }
            $locked->update($values);
            $this->event($locked, $event, $from, $to, $actor, $notes);
            $this->record($request, 'armis.competency.'.strtolower($decision), "ARMIS competency {$to}.", $locked, ['status' => $from], ['status' => $to, 'notes' => $notes]);
            $this->notifyOwner($locked, $actor, strtolower($decision));

            return $locked->fresh($this->relations());
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function revise(Request $request, ArmisCompetency $competency, array $attributes): ArmisCompetency
    {
        $actor = $this->actor($request);
        $competency->loadMissing('resourceProfile');
        $this->assertOfficeScope($actor, $competency->resourceProfile?->office_id);

        return DB::transaction(function () use ($request, $actor, $competency, $attributes): ArmisCompetency {
            $current = ArmisCompetency::query()->lockForUpdate()->findOrFail($competency->id);
            $this->assertLock($current, (int) $attributes['lock_version']);
            abort_unless($current->is_current_revision && $current->status === 'VERIFIED', 409, 'Only a verified current competency can be corrected through a new revision.');
            $evidence = $this->evidence(
                array_key_exists('evidence_document_version_id', $attributes)
                    ? $attributes['evidence_document_version_id']
                    : $current->evidence_document_version_id,
            );
            $catalog = $this->catalogItem((int) ($attributes['competency_id'] ?? $current->competency_id));
            abort_unless($catalog->id === $current->competency_id, 422, 'A competency revision cannot change the Core competency catalogue item.');

            $current->update(['is_current_revision' => false]);
            $revision = ArmisCompetency::query()->create([
                'competency_family_uuid' => $current->competency_family_uuid,
                'resource_profile_id' => $current->resource_profile_id,
                'competency_id' => $current->competency_id,
                'version_number' => $current->version_number + 1,
                'supersedes_id' => $current->id,
                'is_current_revision' => true,
                'proficiency_level' => $attributes['proficiency_level'] ?? $current->proficiency_level,
                'credential_type' => array_key_exists('credential_type', $attributes) ? $attributes['credential_type'] : $current->credential_type,
                'credential_reference' => array_key_exists('credential_reference', $attributes) ? $attributes['credential_reference'] : $current->credential_reference,
                'issuer' => array_key_exists('issuer', $attributes) ? $attributes['issuer'] : $current->issuer,
                'issued_at' => array_key_exists('issued_at', $attributes) ? $attributes['issued_at'] : $current->issued_at,
                'status' => 'DRAFT',
                'evidence_document_version_id' => $evidence?->id,
                'expires_at' => array_key_exists('expires_at', $attributes) ? $attributes['expires_at'] : $current->expires_at,
                'notes' => array_key_exists('notes', $attributes) ? $attributes['notes'] : $current->notes,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->event($current, 'COMPETENCY_SUPERSEDED', 'VERIFIED', 'VERIFIED', $actor, 'Correction created a new immutable competency revision.', ['supersededById' => $revision->id]);
            $this->event($revision, 'COMPETENCY_REVISION_CREATED', null, 'DRAFT', $actor, null, ['supersedesId' => $current->id, 'versionNumber' => $revision->version_number]);
            $this->record($request, 'armis.competency.revised', 'ARMIS competency correction created a new revision.', $revision, ['supersedesId' => $current->id, 'status' => 'VERIFIED'], $revision->toArray());

            return $revision->fresh($this->relations());
        }, 3);
    }

    /** @return Collection<int, ArmisWorkflowEvent> */
    public function events(User $actor, ArmisCompetency $competency): Collection
    {
        $competency->loadMissing('resourceProfile');
        $this->assertOfficeScope($actor, $competency->resourceProfile?->office_id);

        return ArmisWorkflowEvent::query()
            ->where('subject_type', ArmisCompetency::class)
            ->where('subject_id', $competency->id)
            ->with('actor:id,employee_id,name,initials')
            ->latest('created_at')
            ->get();
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        $items = MasterListItem::query()
            ->where('is_active', true)
            ->whereHas('masterList', fn (Builder $query) => $query->whereIn('code', ['ARMIS_COMPETENCY', 'IAP_AUDITOR_SPECIALIZATION']))
            ->with('masterList:id,code,name')
            ->orderBy('label')
            ->get();

        return [
            'statuses' => collect(ArmisCompetency::STATUSES)->map(fn (string $code): array => [
                'code' => $code,
                'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
            ])->values(),
            'proficiencyLevels' => collect(ArmisCompetency::PROFICIENCY_LEVELS)->map(fn (string $code): array => [
                'code' => $code,
                'label' => str($code)->replace('_', ' ')->lower()->headline()->toString(),
            ])->values(),
            'competencies' => $items->map(fn (MasterListItem $item): array => [
                'id' => $item->id,
                'code' => $item->code,
                'label' => $item->label,
                'description' => $item->description,
                'catalogue' => $item->masterList?->code,
            ])->values(),
        ];
    }

    private function profile(User $actor, int $id): ArmisResourceProfile
    {
        $profile = ArmisResourceProfile::query()->find($id);
        abort_unless($profile, 404, 'The linked ARMIS resource profile was not found.');
        $this->assertOfficeScope($actor, $profile->office_id);
        abort_if($profile->status === 'ARCHIVED' || $profile->trashed(), 409, 'Archived ARMIS resource profiles cannot receive competency claims.');

        return $profile;
    }

    private function catalogItem(int $id): MasterListItem
    {
        $item = MasterListItem::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->whereHas('masterList', fn (Builder $query) => $query->whereIn('code', ['ARMIS_COMPETENCY', 'IAP_AUDITOR_SPECIALIZATION']))
            ->first();
        abort_unless($item, 422, 'The selected competency is not an active Core catalogue item.');

        return $item;
    }

    private function evidence(null|int|string $id): ?DocumentVersion
    {
        if ($id === null || $id === '') {
            return null;
        }
        $version = DocumentVersion::query()->with('document')->find((int) $id);
        abort_unless($version && $version->document && ! $version->document->trashed() && $version->document->is_active, 422, 'The evidence must reference an active Core Document Version.');

        return $version;
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    private function assertOfficeScope(User $actor, ?int $officeId): void
    {
        abort_unless($officeId !== null && ($actor->hasGlobalOfficeAccess() || (int) $actor->office_id === $officeId), 403, 'This ARMIS competency is outside your office scope.');
    }

    private function assertLock(ArmisCompetency $competency, int $expected): void
    {
        if ((int) $competency->lock_version !== $expected) {
            throw ValidationException::withMessages(['lockVersion' => ['The ARMIS competency changed in another session. Refresh before continuing.']]);
        }
    }

    private function assertIndependent(User $actor, ArmisCompetency $competency, ArmisResourceProfile $profile): void
    {
        if ((int) $actor->id === (int) $competency->submitted_by || (int) $actor->id === (int) $profile->user_id) {
            throw ValidationException::withMessages(['review' => ['The competency submitter and resource owner cannot independently verify their own certification.']]);
        }
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'resourceProfile.user:id,employee_id,name,initials',
            'resourceProfile.office:id,code,name',
            'competency:id,code,label,description',
            'evidenceDocumentVersion.document:id,title,document_code,is_active,deleted_at',
            'submitter:id,employee_id,name,initials',
            'verifier:id,employee_id,name,initials',
            'reviewer:id,employee_id,name,initials',
            'supersedes:id,version_number,status',
        ];
    }

    private function notifyReviewers(ArmisCompetency $competency, User $actor, string $action): void
    {
        $ids = User::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query
                    ->whereHas('roles.permissions', fn (Builder $permission) => $permission->where('code', 'armis.competency.verify'))
                    ->orWhereHas('role.permissions', fn (Builder $permission) => $permission->where('code', 'armis.competency.verify'));
            })
            ->pluck('id')
            ->reject(fn (int $id): bool => $id === (int) $actor->id)
            ->values();

        DB::afterCommit(fn () => $this->notifications->send($ids, [
            'actorId' => $actor->id,
            'type' => 'ARMIS_COMPETENCY',
            'category' => 'SYSTEM',
            'priority' => 'HIGH',
            'moduleCode' => 'ARMIS',
            'title' => 'ARMIS competency awaiting verification',
            'message' => "{$competency->resourceProfile?->resource_code} has a competency claim awaiting independent verification.",
            'actionUrl' => "/audit-resource-management/resources/{$competency->resource_profile_id}",
            'actionLabel' => 'Review competency',
            'subjectType' => ArmisCompetency::class,
            'subjectId' => $competency->id,
            'subjectCode' => $competency->competency?->code,
            'dedupeKey' => "armis-competency:{$competency->id}:{$competency->lock_version}:{$action}",
        ]));
    }

    private function notifyOwner(ArmisCompetency $competency, User $actor, string $action): void
    {
        $ownerId = $competency->resourceProfile?->user_id;
        if (! $ownerId || (int) $ownerId === (int) $actor->id) {
            return;
        }

        DB::afterCommit(fn () => $this->notifications->send([$ownerId], [
            'actorId' => $actor->id,
            'type' => 'ARMIS_COMPETENCY',
            'category' => 'SYSTEM',
            'priority' => 'NORMAL',
            'moduleCode' => 'ARMIS',
            'title' => 'ARMIS competency review updated',
            'message' => "Your ARMIS competency claim was {$action} by an independent reviewer.",
            'actionUrl' => "/audit-resource-management/resources/{$competency->resource_profile_id}",
            'actionLabel' => 'View competency',
            'subjectType' => ArmisCompetency::class,
            'subjectId' => $competency->id,
            'subjectCode' => $competency->competency?->code,
            'dedupeKey' => "armis-competency:{$competency->id}:{$competency->lock_version}:owner:{$action}",
        ]));
    }

    /** @param array<string, mixed>|null $oldValues @param array<string, mixed>|null $newValues */
    private function record(Request $request, string $action, string $description, ArmisCompetency $competency, ?array $oldValues, ?array $newValues): void
    {
        $actor = $request->user();
        $metadata = [
            'module' => 'ARMIS',
            'resourceProfileId' => $competency->resource_profile_id,
            'competencyId' => $competency->id,
            'competencyCode' => $competency->competency?->code,
            'familyUuid' => $competency->competency_family_uuid,
            'versionNumber' => $competency->version_number,
        ];
        ActivityLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
        AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => ArmisCompetency::class,
            'auditable_id' => $competency->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'metadata' => $metadata,
        ]);
    }

    /** @param array<string, mixed>|null $metadata */
    private function event(ArmisCompetency $competency, string $code, ?string $from, ?string $to, User $actor, ?string $reason = null, ?array $metadata = null): void
    {
        ArmisWorkflowEvent::query()->create([
            'subject_type' => ArmisCompetency::class,
            'subject_id' => $competency->id,
            'event_code' => $code,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor->id,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
