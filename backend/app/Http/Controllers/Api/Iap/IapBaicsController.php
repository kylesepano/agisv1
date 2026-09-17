<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapBaicsAssessmentRequest;
use App\Http\Resources\IapBaicsAssessmentResource;
use App\Models\IapAuditUniverseItem;
use App\Models\IapBaicsAssignment;
use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsVersion;
use App\Models\User;
use App\Services\IapSupport;
use App\Services\NotificationService;
use App\Services\RuntimeConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Controls BAICS-1 foundation cycles without taking ownership of IAP sources. */
class IapBaicsController extends Controller
{
    public function __construct(
        private readonly IapSupport $support,
        private readonly RuntimeConfiguration $runtime,
        private readonly NotificationService $notifications,
        private readonly \App\Services\IapBaicsAssessmentService $controlAssessments,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:'.implode(',', IapBaicsAssessment::STATUSES)],
            'assessmentYear' => ['nullable', 'integer', 'min:2000', 'max:2200'],
            'includeArchived' => ['nullable', 'boolean'],
            'perPage' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);
        $user = $request->user();
        $management = $user->hasGlobalOfficeAccess();
        $query = IapBaicsAssessment::query()
            ->when($management && $request->boolean('includeArchived'), fn ($q) => $q->withTrashed())
            ->with(['responsibleOffice:id,code,name', 'preparer:id,employee_id,name,initials,position'])
            ->when(! $management, fn ($q) => $q->where(function ($scope) use ($user): void {
                $scope->where('responsible_office_id', $user->office_id)
                    ->orWhereHas('scopeItems', fn ($items) => $items->where('office_id', $user->office_id));
            }))
            ->when(($validated['search'] ?? '') !== '', function ($q) use ($validated): void {
                $search = trim((string) $validated['search']);
                $q->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('assessment_code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('scope_summary', 'like', "%{$search}%")
                        ->orWhereHas('responsibleOffice', fn ($office) => $office->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->when(isset($validated['status']), fn ($q) => $q->where('status', $validated['status']))
            ->when(isset($validated['assessmentYear']), fn ($q) => $q->where('assessment_year', $validated['assessmentYear']))
            ->orderByDesc('assessment_year')->orderByDesc('version_number')->orderByDesc('id');
        $records = $query->paginate((int) ($validated['perPage'] ?? $this->runtime->paginationSize()))->withQueryString();
        return response()->json(['success' => true, 'data' => [
            'assessments' => IapBaicsAssessmentResource::collection($records->getCollection()),
            'pagination' => ['currentPage' => $records->currentPage(), 'lastPage' => $records->lastPage(), 'perPage' => $records->perPage(), 'total' => $records->total(), 'from' => $records->firstItem(), 'to' => $records->lastItem()],
        ]]);
    }

    public function store(IapBaicsAssessmentRequest $request): JsonResponse
    {
        $this->assertOfficeScope($request->user(), (int) $request->validated('responsibleOfficeId'));
        $validated = $request->validated();
        $this->validateScopeItems($validated['scopeItems']);
        $assessment = DB::transaction(function () use ($request, $validated): IapBaicsAssessment {
            $sequence = ((int) IapBaicsAssessment::withTrashed()->where('assessment_year', $validated['assessmentYear'])->max('id')) + 1;
            $assessment = IapBaicsAssessment::query()->create([
                'family_uuid' => (string) Str::uuid(),
                'assessment_code' => $validated['assessmentCode'] ?? $this->runtime->formatNumber('baics_number_format', $sequence, ['YEAR' => $validated['assessmentYear']]),
                'version_number' => 1,
                'assessment_year' => $validated['assessmentYear'],
                'name' => $validated['name'],
                'status' => 'DRAFT',
                'responsible_office_id' => $validated['responsibleOfficeId'],
                ...$this->attributes($validated),
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_current_revision' => true,
            ]);
            $this->syncScopeItems($assessment, $validated['scopeItems'], $request->user());
            $this->controlAssessments->initializeComponents($assessment, $request->user());
            $this->syncSnapshot($assessment, $request->user(), 'Created');
            $this->support->audit($request, 'iap.baics.created', $assessment, null, $this->snapshot($assessment));
            return $assessment;
        }, 3);
        return response()->json(['success' => true, 'message' => 'BAICS assessment cycle created successfully.', 'data' => ['assessment' => new IapBaicsAssessmentResource($this->load($assessment))]], 201);
    }

    public function show(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->assertVisible($request->user(), $assessment);
        return response()->json(['success' => true, 'data' => ['assessment' => new IapBaicsAssessmentResource($this->load($assessment))]]);
    }

    public function update(IapBaicsAssessmentRequest $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->assertVisible($request->user(), $assessment);
        $this->assertEditable($assessment);
        $validated = $request->validated();
        $this->assertOfficeScope($request->user(), (int) $validated['responsibleOfficeId']);
        $this->validateScopeItems($validated['scopeItems']);
        DB::transaction(function () use ($request, $assessment, $validated): void {
            $locked = IapBaicsAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            $this->assertEditable($locked);
            $this->assertLock($locked->lock_version, (int) $validated['lockVersion']);
            $old = $this->snapshot($locked);
            $locked->fill([...$this->attributes($validated), 'assessment_code' => $validated['assessmentCode'] ?? $locked->assessment_code, 'responsible_office_id' => $validated['responsibleOfficeId'], 'lock_version' => $locked->lock_version + 1])->save();
            $this->syncScopeItems($locked, $validated['scopeItems'], $request->user());
            $this->syncSnapshot($locked, $request->user(), 'Draft update');
            $this->support->audit($request, 'iap.baics.updated', $locked, $old, $this->snapshot($locked));
        }, 3);
        return response()->json(['success' => true, 'message' => 'BAICS assessment cycle updated successfully.', 'data' => ['assessment' => new IapBaicsAssessmentResource($this->load($assessment->fresh()))]]);
    }

    public function transition(Request $request, IapBaicsAssessment $assessment, string $action): JsonResponse
    {
        $action = strtoupper($action);
        $map = [
            'OPEN' => ['from' => ['DRAFT'], 'to' => 'PLANNING', 'permission' => 'iap.baics.update'],
            'START' => ['from' => ['PLANNING'], 'to' => 'IN_PROGRESS', 'permission' => 'iap.baics.update'],
            'SUBMIT' => ['from' => ['IN_PROGRESS'], 'to' => 'PENDING_REVIEW', 'permission' => 'iap.baics.submit'],
            'RETURN' => ['from' => ['PENDING_REVIEW', 'RESUBMITTED'], 'to' => 'RETURNED', 'permission' => 'iap.baics.return'],
            'RESUBMIT' => ['from' => ['RETURNED'], 'to' => 'RESUBMITTED', 'permission' => 'iap.baics.submit'],
            'APPROVE' => ['from' => ['PENDING_REVIEW', 'RESUBMITTED'], 'to' => 'APPROVED', 'permission' => 'iap.baics.approve'],
            'PUBLISH' => ['from' => ['APPROVED'], 'to' => 'PUBLISHED', 'permission' => 'iap.baics.publish'],
            'ARCHIVE' => ['from' => ['PUBLISHED'], 'to' => 'ARCHIVED', 'permission' => 'iap.baics.archive'],
        ];
        abort_unless(isset($map[$action]), 404);
        abort_unless($request->user()->hasPermission($map[$action]['permission']), 403);
        $validated = $request->validate(['lockVersion' => ['required', 'integer', 'min:1'], 'comment' => ['nullable', 'string', 'max:10000']]);
        if ($action === 'RETURN' && trim((string) ($validated['comment'] ?? '')) === '') throw ValidationException::withMessages(['comment' => ['A return reason is required.']]);
        $updated = DB::transaction(function () use ($request, $assessment, $validated, $action, $map): IapBaicsAssessment {
            $locked = IapBaicsAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            $this->assertVisible($request->user(), $locked);
            $this->assertLock($locked->lock_version, (int) $validated['lockVersion']);
            if (! in_array($locked->status, $map[$action]['from'], true)) throw ValidationException::withMessages(['status' => ["{$action} is not available while this cycle is {$locked->status}."]]);
            if ($action === 'APPROVE' && (int) $locked->prepared_by === (int) $request->user()->id) throw ValidationException::withMessages(['approver' => ['The preparer cannot approve the same BAICS cycle.']]);
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) $this->assertReady($locked, false);
            if ($action === 'APPROVE') $this->assertReady($locked, true);
            $oldStatus = $locked->status;
            $attributes = ['status' => $map[$action]['to'], 'lock_version' => $locked->lock_version + 1];
            if ($action === 'SUBMIT' || $action === 'RESUBMIT') $attributes += ['submitted_by' => $request->user()->id, 'submitted_at' => now()];
            if ($action === 'RETURN') $attributes += ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()];
            if ($action === 'APPROVE') $attributes += ['approved_by' => $request->user()->id, 'approved_at' => now()];
            if ($action === 'PUBLISH') $attributes += ['published_by' => $request->user()->id, 'published_at' => now()];
            if ($action === 'ARCHIVE') $attributes += ['archived_at' => now(), 'is_current_revision' => false];
            $locked->forceFill($attributes)->save();
            $this->syncSnapshot($locked, $request->user(), $validated['comment'] ?? $action);
            $this->support->audit($request, 'iap.baics.'.strtolower($action), $locked, ['status' => $oldStatus], ['status' => $locked->status], ['comment' => $validated['comment'] ?? null]);
            $this->notifyTransition($locked, $request->user(), $action);
            return $locked;
        }, 3);
        return response()->json(['success' => true, 'message' => 'BAICS workflow updated successfully.', 'data' => ['assessment' => new IapBaicsAssessmentResource($this->load($updated))]]);
    }

    public function revision(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        abort_unless($request->user()->hasPermission('iap.baics.update'), 403);
        $this->assertVisible($request->user(), $assessment);
        if (! in_array($assessment->status, ['APPROVED', 'PUBLISHED'], true)) throw ValidationException::withMessages(['status' => ['Only an approved or published BAICS cycle can be revised.']]);
        $revision = DB::transaction(function () use ($request, $assessment): IapBaicsAssessment {
            $old = IapBaicsAssessment::query()->lockForUpdate()->findOrFail($assessment->id);
            $next = (int) IapBaicsAssessment::withTrashed()->where('family_uuid', $old->family_uuid)->max('version_number') + 1;
            $revision = IapBaicsAssessment::query()->create([
                ...$old->only(['family_uuid', 'assessment_year', 'name', 'responsible_office_id', 'scope_summary', 'objectives', 'boundaries', 'exclusions', 'limitations', 'methodology', 'planned_start_date', 'planned_end_date', 'review_date', 'report_date', 'legacy_status', 'legacy_reason', 'legacy_authority_user_id', 'legacy_expires_at']),
                'assessment_code' => $old->assessment_code.'-R'.str_pad((string) $next, 2, '0', STR_PAD_LEFT),
                'version_number' => $next, 'status' => 'DRAFT', 'prepared_by' => $request->user()->id,
                'supersedes_id' => $old->id, 'lock_version' => 1, 'is_current_revision' => true,
            ]);
            $old->forceFill(['is_current_revision' => false])->save();
            foreach ($old->scopeItems as $item) $revision->scopeItems()->create($item->only(['audit_universe_item_id', 'office_id', 'audit_area_id', 'audit_focus_id', 'source_snapshot', 'scope_notes', 'boundaries', 'exclusions', 'limitations']) + ['created_by' => $request->user()->id]);
            foreach ($old->assignments()->where('status', 'ASSIGNED')->get() as $assignment) $revision->assignments()->create($assignment->only(['user_id', 'role_code', 'authority_level', 'assignment_reason', 'status']) + ['assigned_at' => now(), 'assigned_by' => $request->user()->id]);
            $this->controlAssessments->cloneComponents($old, $revision, $request->user());
            $this->syncSnapshot($revision, $request->user(), 'Revision created');
            $this->support->audit($request, 'iap.baics.revision_created', $revision, ['supersedesId' => $old->id], $this->snapshot($revision));
            return $revision;
        }, 3);
        return response()->json(['success' => true, 'message' => 'BAICS revision created successfully.', 'data' => ['assessment' => new IapBaicsAssessmentResource($this->load($revision))]], 201);
    }

    public function versions(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        $this->assertVisible($request->user(), $assessment);
        $family = IapBaicsAssessment::withTrashed()->where('family_uuid', $assessment->family_uuid)->with(['responsibleOffice:id,code,name', 'preparer:id,employee_id,name,initials,position', 'versions.creator:id,employee_id,name,initials'])->orderBy('version_number')->get();
        return response()->json(['success' => true, 'data' => ['versions' => IapBaicsAssessmentResource::collection($family)]]);
    }

    public function storeAssignment(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        abort_unless($request->user()->hasPermission('iap.baics.assign'), 403);
        $this->assertVisible($request->user(), $assessment);
        if (in_array($assessment->status, ['APPROVED', 'PUBLISHED', 'ARCHIVED'], true)) throw ValidationException::withMessages(['status' => ['Assignments cannot be changed after approval.']]);
        $validated = $request->validate(['userId' => ['required', 'integer', 'exists:users,id'], 'roleCode' => ['required', 'in:COORDINATOR,ASSESSOR,REVIEWER,APPROVER,RESPONDENT'], 'authorityLevel' => ['nullable', 'string', 'max:40'], 'assignmentReason' => ['nullable', 'string', 'max:4000']]);
        $user = User::query()->findOrFail($validated['userId']);
        if (! $user->is_active) throw ValidationException::withMessages(['userId' => ['The selected user is inactive.']]);
        $assignment = DB::transaction(function () use ($request, $assessment, $validated): IapBaicsAssignment {
            $assignment = IapBaicsAssignment::query()->updateOrCreate(
                ['assessment_id' => $assessment->id, 'user_id' => $validated['userId'], 'role_code' => $validated['roleCode']],
                ['authority_level' => $validated['authorityLevel'] ?? null, 'assignment_reason' => $validated['assignmentReason'] ?? null, 'assigned_at' => now(), 'assigned_by' => $request->user()->id, 'status' => 'ASSIGNED', 'lock_version' => 1],
            );
            $this->support->audit($request, 'iap.baics.assignment.created', $assessment, null, ['assignmentId' => $assignment->id, 'userId' => $assignment->user_id, 'roleCode' => $assignment->role_code]);
            $this->notifications->send([$assignment->user_id], ['actorId' => $request->user()->id, 'type' => 'BAICS_ASSIGNMENT', 'category' => 'ASSIGNMENT', 'moduleCode' => 'IAP', 'title' => 'BAICS assessment assignment', 'message' => "You were assigned to {$assessment->assessment_code} as {$assignment->role_code}.", 'actionUrl' => '/internal-audit-planning/baics', 'actionLabel' => 'Open BAICS', 'subjectType' => IapBaicsAssessment::class, 'subjectId' => $assessment->id, 'subjectCode' => $assessment->assessment_code, 'dedupeKey' => "baics:assignment:{$assignment->id}:{$assignment->lock_version}"]);
            return $assignment;
        }, 3);
        $assignment->load('user:id,employee_id,name,initials,position');
        return response()->json(['success' => true, 'message' => 'BAICS assignment saved.', 'data' => ['assignment' => [
            'id' => $assignment->id, 'assessmentId' => $assignment->assessment_id, 'userId' => $assignment->user_id,
            'user' => $assignment->user?->only(['id', 'employee_id', 'name', 'initials', 'position']),
            'roleCode' => $assignment->role_code, 'authorityLevel' => $assignment->authority_level,
            'assignmentReason' => $assignment->assignment_reason, 'status' => $assignment->status,
            'assignedAt' => $assignment->assigned_at?->toISOString(), 'lockVersion' => $assignment->lock_version,
        ]]], 201);
    }

    public function endAssignment(Request $request, IapBaicsAssessment $assessment, IapBaicsAssignment $assignment): JsonResponse
    {
        abort_unless($request->user()->hasPermission('iap.baics.assign'), 403);
        abort_unless((int) $assignment->assessment_id === (int) $assessment->id, 404);
        $assignment->forceFill(['status' => 'ENDED', 'ended_at' => now(), 'lock_version' => $assignment->lock_version + 1])->save();
        $this->support->audit($request, 'iap.baics.assignment.ended', $assessment, null, ['assignmentId' => $assignment->id]);
        return response()->json(['success' => true, 'message' => 'BAICS assignment ended.']);
    }

    public function destroy(Request $request, IapBaicsAssessment $assessment): JsonResponse
    {
        abort_unless($request->user()->hasPermission('iap.baics.archive'), 403);
        if (! in_array($assessment->status, ['DRAFT', 'ARCHIVED'], true)) throw ValidationException::withMessages(['status' => ['Only draft cycles may be archived before publication.']]);
        $assessment->delete();
        $this->support->audit($request, 'iap.baics.archived', $assessment, null, ['status' => $assessment->status]);
        return response()->json(['success' => true, 'message' => 'BAICS assessment archived.']);
    }

    public function restore(Request $request, int $assessment): JsonResponse
    {
        abort_unless($request->user()->hasPermission('iap.baics.archive'), 403);
        $record = IapBaicsAssessment::onlyTrashed()->findOrFail($assessment);
        $record->restore();
        $record->forceFill(['status' => 'DRAFT', 'is_current_revision' => true, 'lock_version' => $record->lock_version + 1])->save();
        $this->support->audit($request, 'iap.baics.restored', $record, ['status' => 'ARCHIVED'], ['status' => 'DRAFT']);
        return response()->json(['success' => true, 'message' => 'BAICS assessment restored.', 'data' => ['assessment' => new IapBaicsAssessmentResource($this->load($record))]]);
    }

    /** @param array<string, mixed> $validated */
    private function attributes(array $validated): array
    {
        return collect($validated)->only(['assessmentYear', 'name', 'scopeSummary', 'objectives', 'boundaries', 'exclusions', 'limitations', 'methodology', 'plannedStartDate', 'plannedEndDate', 'reviewDate', 'reportDate', 'legacyStatus', 'legacyReason', 'legacyAuthorityUserId', 'legacyExpiresAt'])->mapWithKeys(fn ($value, $key) => [Str::snake($key) => $value])->all();
    }

    /** @param array<int, array<string, mixed>> $items */
    private function validateScopeItems(array $items): void
    {
        foreach ($items as $item) {
            $source = IapAuditUniverseItem::query()->with(['responsibleOffice', 'stakeholderOffices'])->findOrFail($item['auditUniverseItemId']);
            $allowedOffice = (int) $source->responsible_office_id === (int) $item['officeId'] || $source->stakeholderOffices->contains('id', (int) $item['officeId']);
            if (! $allowedOffice) throw ValidationException::withMessages(['scopeItems' => ['Every BAICS scope office must be an owner or stakeholder office of its Audit Universe source.']]);
            if (! DB::table('audit_area_office')->where('office_id', $item['officeId'])->where('audit_area_id', $item['auditAreaId'])->exists()) throw ValidationException::withMessages(['scopeItems' => ['Each BAICS Area must be covered by its scope office.']]);
            if (! DB::table('audit_focuses')->where('id', $item['auditFocusId'])->where('audit_area_id', $item['auditAreaId'])->whereNull('deleted_at')->exists()) throw ValidationException::withMessages(['scopeItems' => ['Each BAICS Focus must belong to its selected Audit Area.']]);
        }
    }

    /** @param array<int, array<string, mixed>> $items */
    private function syncScopeItems(IapBaicsAssessment $assessment, array $items, User $actor): void
    {
        $assessment->scopeItems()->delete();
        foreach ($items as $item) {
            $source = IapAuditUniverseItem::query()->with(['responsibleOffice:id,code,name', 'primaryAuditArea:id,code,name', 'stakeholderOffices:id,code,name'])->findOrFail($item['auditUniverseItemId']);
            $assessment->scopeItems()->create([
                'audit_universe_item_id' => $source->id, 'office_id' => $item['officeId'], 'audit_area_id' => $item['auditAreaId'], 'audit_focus_id' => $item['auditFocusId'],
                'source_snapshot' => ['id' => $source->id, 'subjectCode' => $source->subject_code, 'name' => $source->name, 'responsibleOffice' => $source->responsibleOffice?->only(['id', 'code', 'name']), 'primaryAuditArea' => $source->primaryAuditArea?->only(['id', 'code', 'name']), 'capturedAt' => now()->toISOString()],
                'scope_notes' => $item['scopeNotes'] ?? null, 'boundaries' => $item['boundaries'] ?? null, 'exclusions' => $item['exclusions'] ?? null, 'limitations' => $item['limitations'] ?? null, 'created_by' => $actor->id,
            ]);
        }
    }

    private function assertOfficeScope(User $user, int $officeId): void { abort_unless($user->hasGlobalOfficeAccess() || (int) $user->office_id === $officeId, 403, 'This BAICS cycle is outside your office scope.'); }
    private function assertVisible(User $user, IapBaicsAssessment $assessment): void { abort_unless($user->hasGlobalOfficeAccess() || (int) $assessment->responsible_office_id === (int) $user->office_id || $assessment->scopeItems()->where('office_id', $user->office_id)->exists(), 403, 'This BAICS cycle is outside your office scope.'); }
    private function assertEditable(IapBaicsAssessment $assessment): void { if (! in_array($assessment->status, ['DRAFT', 'PLANNING', 'IN_PROGRESS', 'RETURNED'], true)) throw ValidationException::withMessages(['status' => ['Only draft or returned BAICS cycles can be edited.']]); }
    private function assertLock(int $current, int $provided): void { if ($current !== $provided) throw ValidationException::withMessages(['lockVersion' => ['This BAICS cycle changed. Refresh before continuing.']]); }
    private function assertReady(IapBaicsAssessment $assessment, bool $requireApprovedComponents = false): void
    {
        $assessment->loadMissing(['scopeItems', 'assignments']);
        $checks = ['responsibleOffice' => (bool) $assessment->responsible_office_id, 'scope' => $assessment->scopeItems->isNotEmpty(), 'objectives' => filled($assessment->objectives), 'methodology' => filled($assessment->methodology), 'team' => $assessment->assignments->where('status', 'ASSIGNED')->isNotEmpty(), 'scopeDimensions' => $assessment->scopeItems->every(fn ($item) => $item->office_id && $item->audit_area_id && $item->audit_focus_id)];
        if (in_array(false, $checks, true)) throw ValidationException::withMessages(['readiness' => ['The BAICS cycle is not ready. Complete responsible office, scope, Area/Focus, objectives, methodology, and at least one active assignment.']]);
        if ($requireApprovedComponents) $this->controlAssessments->assertReady($assessment, true);
    }
    private function readiness(IapBaicsAssessment $assessment): array
    {
        $assessment->loadMissing(['scopeItems', 'assignments']);
        $checks = ['responsibleOffice' => (bool) $assessment->responsible_office_id, 'scope' => $assessment->scopeItems->isNotEmpty(), 'objectives' => filled($assessment->objectives), 'methodology' => filled($assessment->methodology), 'team' => $assessment->assignments->where('status', 'ASSIGNED')->isNotEmpty(), 'scopeDimensions' => $assessment->scopeItems->every(fn ($item) => $item->office_id && $item->audit_area_id && $item->audit_focus_id)];
        $componentReadiness = $this->controlAssessments->readiness($assessment);
        $checks['controlComponents'] = $componentReadiness['ready'];
        return ['ready' => ! in_array(false, $checks, true), 'checks' => $checks, 'components' => $componentReadiness['components']];
    }
    private function snapshot(IapBaicsAssessment $assessment): array { $assessment->loadMissing(['scopeItems', 'assignments', 'components']); return [...$assessment->only(['id', 'family_uuid', 'assessment_code', 'version_number', 'assessment_year', 'name', 'status', 'responsible_office_id', 'scope_summary', 'objectives', 'methodology', 'lock_version']), 'scopeItems' => $assessment->scopeItems->map(fn ($item) => $item->only(['audit_universe_item_id', 'office_id', 'audit_area_id', 'audit_focus_id', 'source_snapshot']))->values()->all(), 'assignments' => $assessment->assignments->map(fn ($item) => $item->only(['user_id', 'role_code', 'status']))->values()->all(), 'components' => $assessment->components->map(fn ($component) => ['id' => $component->id, 'componentCode' => $component->component_code, 'status' => $component->status, 'conclusion' => $component->conclusion, 'versionNumber' => $component->version_number])->values()->all()]; }
    private function syncSnapshot(IapBaicsAssessment $assessment, User $actor, string $reason): void { $snapshot = $this->snapshot($assessment); IapBaicsVersion::query()->create(['assessment_id' => $assessment->id, 'family_uuid' => $assessment->family_uuid, 'version_number' => $assessment->version_number, 'status' => $assessment->status, 'snapshot' => $snapshot, 'snapshot_hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'reason' => $reason, 'created_by' => $actor->id]); }
    private function load(IapBaicsAssessment $assessment): IapBaicsAssessment { return $assessment->load(['responsibleOffice:id,code,name', 'preparer:id,employee_id,name,initials,position', 'submitter:id,employee_id,name,initials,position', 'reviewer:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position', 'publisher:id,employee_id,name,initials,position', 'scopeItems.auditUniverseItem:id,subject_code,name', 'scopeItems.office:id,code,name', 'scopeItems.auditArea:id,code,name', 'scopeItems.auditFocus:id,code,name', 'assignments.user:id,employee_id,name,initials,position', 'versions.creator:id,employee_id,name,initials', 'components'])->setAttribute('readiness', $this->readiness($assessment))->setAttribute('available_actions', $this->availableActions($assessment)); }
    private function availableActions(IapBaicsAssessment $assessment): array { return ['DRAFT' => ['OPEN'], 'PLANNING' => ['START'], 'IN_PROGRESS' => ['SUBMIT'], 'PENDING_REVIEW' => ['RETURN', 'APPROVE'], 'RETURNED' => ['RESUBMIT'], 'RESUBMITTED' => ['RETURN', 'APPROVE'], 'APPROVED' => ['PUBLISH'], 'PUBLISHED' => ['ARCHIVE']][$assessment->status] ?? []; }
    private function notifyTransition(IapBaicsAssessment $assessment, User $actor, string $action): void
    {
        $recipients = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($assessment): void {
                $query->where('office_id', $assessment->responsible_office_id)
                    ->orWhereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.baics.review'));
            })
            ->pluck('id');
        $this->notifications->send($recipients, ['actorId' => $actor->id, 'type' => 'BAICS_WORKFLOW', 'category' => 'WORKFLOW', 'moduleCode' => 'IAP', 'title' => "BAICS {$action}", 'message' => "{$assessment->assessment_code} moved to {$assessment->status}.", 'actionUrl' => '/internal-audit-planning/baics', 'actionLabel' => 'Open BAICS', 'subjectType' => IapBaicsAssessment::class, 'subjectId' => $assessment->id, 'subjectCode' => $assessment->assessment_code, 'dedupeKey' => "baics:workflow:{$assessment->id}:{$assessment->lock_version}"]);
    }
}
