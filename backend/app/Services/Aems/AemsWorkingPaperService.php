<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditEvidence;
use App\Models\AuditProgramProcedure;
use App\Models\EngagementEvent;
use App\Models\WorkingPaper;
use App\Models\WorkingPaperVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Implements immutable Working Paper content versions and controlled review.
 */
class AemsWorkingPaperService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
        private readonly AemsEvidenceService $evidence,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(Request $request, AuditEngagement $engagement): array
    {
        $engagement->loadMissing('offices:id,code,name');
        $papers = WorkingPaper::query()
            ->visibleTo($request->user())
            ->where('audit_engagement_id', $engagement->id)
            ->with([
                'procedure.program',
                'preparer:id,employee_id,name,initials',
                'reviewer:id,employee_id,name,initials',
                'versions.creator:id,employee_id,name,initials',
                'versions.evidence.category',
                'versions.evidence.sourceType',
                'versions.evidence.confidentialityLevel',
                'versions.evidence.documentVersion',
            ])
            ->orderBy('working_paper_code')
            ->get();
        $procedures = AuditProgramProcedure::query()
            ->whereHas('program', fn ($program) => $program
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->where('status', 'ACTIVE'))
            ->with(['program:id,program_code,title,status', 'assignee:id,employee_id,name,initials'])
            ->orderBy('audit_program_id')
            ->orderBy('sequence_number')
            ->get();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'fieldworkAvailable' => $procedures->isNotEmpty(),
            'custodianOffices' => $engagement->offices
                ->map(fn ($office): array => $office->only(['id', 'code', 'name']))
                ->values(),
            'procedures' => $procedures->map(fn (AuditProgramProcedure $procedure): array => [
                'id' => $procedure->id,
                'procedureCode' => $procedure->procedure_code,
                'objective' => $procedure->objective,
                'description' => $procedure->procedure_description,
                'expectedEvidence' => $procedure->expected_evidence,
                'status' => $procedure->status,
                'targetDate' => $procedure->target_date?->toDateString(),
                'program' => $procedure->program?->only(['id', 'program_code', 'title', 'status']),
                'assignee' => $this->user($procedure->assignee),
            ])->values(),
            'workingPapers' => $papers->map(fn (WorkingPaper $paper): array => $this->data($paper))
                ->values(),
            ...$this->evidence->workspace($request, $engagement),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): WorkingPaper {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.working-paper.create',
        );

        $paper = DB::transaction(function () use (
            $request,
            $engagement,
            $attributes,
        ): WorkingPaper {
            $procedure = $this->procedure(
                $request,
                $engagement,
                (int) $attributes['procedureId'],
            );
            $evidence = $this->resolveEvidence(
                $request,
                $engagement,
                $attributes['evidenceIds'] ?? [],
            );
            $this->ensureEvidenceExplanation($evidence, $attributes['noEvidenceReason'] ?? null);
            $code = $this->nextCode($engagement);
            $paper = WorkingPaper::query()->create([
                'audit_engagement_id' => $engagement->id,
                'audit_program_procedure_id' => $procedure->id,
                'working_paper_code' => $code,
                'title' => $attributes['title'],
                'status' => 'DRAFT',
                'current_version_number' => 1,
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $version = $this->createVersion($request, $paper, $attributes, 1, $evidence);

            $currentReference = trim((string) $procedure->working_paper_reference);
            $references = collect(preg_split('/\s*,\s*/', $currentReference, -1, PREG_SPLIT_NO_EMPTY))
                ->push($code)
                ->unique()
                ->implode(', ');
            $procedure->forceFill([
                'working_paper_reference' => mb_substr($references, 0, 120),
                'lock_version' => $procedure->lock_version + 1,
            ])->save();
            $procedure->program()->increment('lock_version');

            $this->event(
                $request,
                $engagement,
                $paper,
                $version,
                'CREATED',
                null,
                'DRAFT',
                null,
                $this->auditValues($paper, $version),
            );
            $this->support->audit(
                $request,
                'aems.working-paper.created',
                $engagement,
                null,
                $this->auditValues($paper, $version),
                ['workingPaperId' => $paper->id, 'procedureId' => $procedure->id],
            );

            return $paper;
        }, 3);

        return $this->load($paper);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        Request $request,
        AuditEngagement $engagement,
        WorkingPaper $paper,
        array $attributes,
    ): WorkingPaper {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.working-paper.create',
        );

        $paper = DB::transaction(function () use (
            $request,
            $engagement,
            $paper,
            $attributes,
        ): WorkingPaper {
            $locked = $this->lockPaper(
                $engagement,
                $paper,
                (int) $attributes['lockVersion'],
            );
            if (! in_array($locked->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft or returned Working Papers can be edited.'],
                ]);
            }
            if ((int) $attributes['procedureId'] !== (int) $locked->audit_program_procedure_id) {
                throw ValidationException::withMessages([
                    'procedureId' => ['A Working Paper cannot be moved to another audit procedure.'],
                ]);
            }
            $this->procedure($request, $engagement, (int) $attributes['procedureId']);
            $evidence = $this->resolveEvidence(
                $request,
                $engagement,
                $attributes['evidenceIds'] ?? [],
            );
            $this->ensureEvidenceExplanation($evidence, $attributes['noEvidenceReason'] ?? null);
            $before = $this->auditValues($locked, $locked->latestVersion()->firstOrFail());
            $number = $locked->current_version_number + 1;
            $version = $this->createVersion($request, $locked, $attributes, $number, $evidence);
            $locked->update([
                'title' => $attributes['title'],
                'current_version_number' => $number,
                'reviewer_id' => null,
                'reviewed_at' => null,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $after = $this->auditValues($locked, $version);
            $this->event(
                $request,
                $engagement,
                $locked,
                $version,
                'VERSION_CREATED',
                $locked->status,
                $locked->status,
                $before,
                $after,
                $attributes['changeReason'],
            );
            $this->support->audit(
                $request,
                'aems.working-paper.version_created',
                $engagement,
                $before,
                $after,
                ['workingPaperId' => $locked->id, 'changeReason' => $attributes['changeReason']],
            );

            return $locked;
        }, 3);

        return $this->load($paper);
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        WorkingPaper $paper,
        string $action,
        int $lockVersion,
        ?string $comment,
    ): WorkingPaper {
        $permission = match ($action) {
            'SUBMIT', 'RESUBMIT' => 'aems.working-paper.create',
            'RETURN' => 'aems.working-paper.review',
            'APPROVE' => 'aems.working-paper.approve',
            'VOID' => 'aems.working-paper.review',
            default => throw ValidationException::withMessages([
                'action' => ['Unsupported Working Paper action.'],
            ]),
        };
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permission,
            in_array($action, ['RETURN', 'APPROVE', 'VOID'], true)
                ? $paper->prepared_by : null,
        );

        $paper = DB::transaction(function () use (
            $request,
            $engagement,
            $paper,
            $action,
            $lockVersion,
            $comment,
        ): WorkingPaper {
            $locked = $this->lockPaper($engagement, $paper, $lockVersion);
            $version = $locked->latestVersion()
                ->with(['evidence.documentVersion'])
                ->firstOrFail();
            $from = $locked->status;
            $to = $this->nextStatus($from, $action);
            if (in_array($action, ['RETURN', 'VOID'], true)
                && mb_strlen(trim((string) $comment)) < 5) {
                throw ValidationException::withMessages([
                    'comment' => ["A clear Working Paper {$action} reason is required."],
                ]);
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT', 'APPROVE'], true)) {
                $this->ensureSubmittable($locked, $version);
            }

            $changes = [
                'status' => $to,
                'lock_version' => $locked->lock_version + 1,
            ];
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $changes['submitted_by'] = $request->user()->id;
                $changes['submitted_at'] = now();
                $changes['reviewer_id'] = null;
                $changes['reviewed_at'] = null;
            }
            if (in_array($action, ['RETURN', 'APPROVE'], true)) {
                $changes['reviewer_id'] = $request->user()->id;
                $changes['reviewed_at'] = now();
            }
            if ($action === 'APPROVE') {
                foreach ($version->evidence as $linkedEvidence) {
                    $lockedEvidence = AuditEvidence::query()
                        ->lockForUpdate()
                        ->findOrFail($linkedEvidence->id);
                    if (! in_array($lockedEvidence->status, ['VERIFIED', 'LOCKED'], true)) {
                        throw ValidationException::withMessages([
                            'evidenceIds' => ['Every cited evidence version must be verified before approval.'],
                        ]);
                    }
                    if ($lockedEvidence->status === 'VERIFIED') {
                        $lockedEvidence->update([
                            'status' => 'LOCKED',
                            'locked_at' => now(),
                            'lock_version' => $lockedEvidence->lock_version + 1,
                        ]);
                    }
                }
                $changes['approved_by'] = $request->user()->id;
                $changes['approved_at'] = now();
            }
            if ($action === 'VOID') {
                $changes['voided_by'] = $request->user()->id;
                $changes['voided_at'] = now();
                $changes['void_reason'] = $comment;
                $changes['is_active'] = false;
            }
            $locked->update($changes);
            $before = ['status' => $from, 'versionNumber' => $version->version_number];
            $after = ['status' => $to, 'versionNumber' => $version->version_number];
            $this->event(
                $request,
                $engagement,
                $locked,
                $version,
                $action,
                $from,
                $to,
                $before,
                $after,
                $comment,
            );
            $this->support->audit(
                $request,
                'aems.working-paper.'.str($action)->lower(),
                $engagement,
                $before,
                $after,
                ['workingPaperId' => $locked->id, 'comment' => $comment],
            );
            if ($action === 'RETURN') {
                $this->notifications->workingPaperReturned(
                    $request,
                    $engagement,
                    $locked,
                    $version->version_number,
                    $comment,
                );
            }

            return $locked;
        }, 3);

        return $this->load($paper);
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        WorkingPaper $paper,
        int $lockVersion,
        string $reason,
    ): WorkingPaper {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.working-paper.create',
        );

        $paper = DB::transaction(function () use (
            $request,
            $engagement,
            $paper,
            $lockVersion,
            $reason,
        ): WorkingPaper {
            $locked = $this->lockPaper($engagement, $paper, $lockVersion);
            if ($locked->status !== 'APPROVED') {
                throw ValidationException::withMessages([
                    'status' => ['Only an approved Working Paper can start a correction revision.'],
                ]);
            }
            $source = $locked->latestVersion()->with('evidence')->firstOrFail();
            $number = $locked->current_version_number + 1;
            $version = WorkingPaperVersion::query()->create([
                ...$source->only([
                    'objective',
                    'procedure_performed',
                    'population_description',
                    'sample_description',
                    'result',
                    'conclusion',
                    'no_evidence_reason',
                    'cross_references',
                    'document_version_id',
                    'checksum_sha256',
                ]),
                'working_paper_id' => $locked->id,
                'version_number' => $number,
                'change_reason' => $reason,
                'created_by' => $request->user()->id,
            ]);
            if ($source->evidence->isNotEmpty()) {
                $version->evidence()->attach($source->evidence->modelKeys());
            }
            $locked->update([
                'status' => 'DRAFT',
                'current_version_number' => $number,
                'prepared_by' => $request->user()->id,
                'reviewer_id' => null,
                'reviewed_at' => null,
                'submitted_by' => null,
                'submitted_at' => null,
                'approved_by' => null,
                'approved_at' => null,
                'lock_version' => $locked->lock_version + 1,
                'is_active' => true,
            ]);
            $this->event(
                $request,
                $engagement,
                $locked,
                $version,
                'REVISE',
                'APPROVED',
                'DRAFT',
                ['status' => 'APPROVED', 'versionNumber' => $source->version_number],
                ['status' => 'DRAFT', 'versionNumber' => $number],
                $reason,
            );
            $this->support->audit(
                $request,
                'aems.working-paper.revision_started',
                $engagement,
                ['status' => 'APPROVED', 'versionNumber' => $source->version_number],
                ['status' => 'DRAFT', 'versionNumber' => $number],
                ['workingPaperId' => $locked->id, 'reason' => $reason],
            );

            return $locked;
        }, 3);

        return $this->load($paper);
    }

    /** @return array<string, mixed> */
    public function data(WorkingPaper $paper): array
    {
        $paper = $this->load($paper);
        $latest = $paper->versions->sortByDesc('version_number')->first();

        return [
            'id' => $paper->id,
            'workingPaperCode' => $paper->working_paper_code,
            'title' => $paper->title,
            'status' => $paper->status,
            'procedureId' => $paper->audit_program_procedure_id,
            'procedure' => $paper->procedure ? [
                'id' => $paper->procedure->id,
                'procedureCode' => $paper->procedure->procedure_code,
                'objective' => $paper->procedure->objective,
                'program' => $paper->procedure->program?->only(['id', 'program_code', 'title', 'status']),
            ] : null,
            'currentVersionNumber' => $paper->current_version_number,
            'latestVersion' => $latest ? $this->versionData($latest) : null,
            'versions' => $paper->versions->sortByDesc('version_number')
                ->map(fn (WorkingPaperVersion $version): array => $this->versionData($version))
                ->values(),
            'preparedBy' => $this->user($paper->preparer),
            'preparedAt' => $paper->created_at?->toIso8601String(),
            'reviewedBy' => $this->user($paper->reviewer),
            'reviewedAt' => $paper->reviewed_at?->toIso8601String(),
            'submittedAt' => $paper->submitted_at?->toIso8601String(),
            'approvedAt' => $paper->approved_at?->toIso8601String(),
            'voidedAt' => $paper->voided_at?->toIso8601String(),
            'voidReason' => $paper->void_reason,
            'lockVersion' => $paper->lock_version,
            'isActive' => $paper->is_active,
            'events' => EngagementEvent::query()
                ->where('audit_engagement_id', $paper->audit_engagement_id)
                ->where('subject_type', 'WORKING_PAPER')
                ->where('subject_id', $paper->id)
                ->with('actor:id,employee_id,name,initials')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (EngagementEvent $event): array => [
                    'id' => $event->id,
                    'action' => $event->action,
                    'fromStatus' => $event->from_status,
                    'toStatus' => $event->to_status,
                    'subjectVersion' => $event->subject_version,
                    'comment' => $event->comment,
                    'actor' => $this->user($event->actor),
                    'createdAt' => $event->created_at?->toIso8601String(),
                ])->values(),
        ];
    }

    private function load(WorkingPaper $paper): WorkingPaper
    {
        return $paper->fresh([
            'procedure.program',
            'preparer:id,employee_id,name,initials',
            'reviewer:id,employee_id,name,initials',
            'versions.creator:id,employee_id,name,initials',
            'versions.evidence.category',
            'versions.evidence.sourceType',
            'versions.evidence.confidentialityLevel',
            'versions.evidence.documentVersion',
        ]);
    }

    private function procedure(
        Request $request,
        AuditEngagement $engagement,
        int $procedureId,
    ): AuditProgramProcedure {
        $procedure = AuditProgramProcedure::query()
            ->whereKey($procedureId)
            ->whereHas('program', fn ($program) => $program
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->where('status', 'ACTIVE'))
            ->with('program')
            ->lockForUpdate()
            ->first();
        if (! $procedure) {
            throw ValidationException::withMessages([
                'procedureId' => ['Choose a procedure from the current active Audit Program.'],
            ]);
        }
        if (! $request->user()->hasPermission('aems.working-paper.manage')) {
            $assignmentRole = $engagement->teamMembers()
                ->where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->value('assignment_role_code');
            if ($assignmentRole === 'AUDITOR'
                && (int) $procedure->assigned_to !== (int) $request->user()->id) {
                throw ValidationException::withMessages([
                    'procedureId' => ['An Auditor may prepare Working Papers only for procedures assigned to them.'],
                ]);
            }
        }

        return $procedure;
    }

    /** @param list<int|string> $ids
     * @return Collection<int, AuditEvidence>
     */
    private function resolveEvidence(
        Request $request,
        AuditEngagement $engagement,
        array $ids,
    ): Collection {
        $ids = collect($ids)->map(fn ($id): int => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        $records = AuditEvidence::query()
            ->visibleTo($request->user())
            ->where('audit_engagement_id', $engagement->id)
            ->whereIn('id', $ids)
            ->whereNot('status', 'VOIDED')
            ->get();
        if ($records->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'evidenceIds' => ['Every evidence version must belong to this engagement, be visible, and not be voided.'],
            ]);
        }

        return $records;
    }

    private function ensureEvidenceExplanation(Collection $evidence, ?string $reason): void
    {
        if ($evidence->isEmpty() && mb_strlen(trim((string) $reason)) < 5) {
            throw ValidationException::withMessages([
                'noEvidenceReason' => ['Attach evidence or explain why no evidence file is required.'],
            ]);
        }
    }

    private function createVersion(
        Request $request,
        WorkingPaper $paper,
        array $attributes,
        int $number,
        Collection $evidence,
    ): WorkingPaperVersion {
        $version = WorkingPaperVersion::query()->create([
            'working_paper_id' => $paper->id,
            'version_number' => $number,
            'objective' => $attributes['objective'],
            'procedure_performed' => $attributes['procedurePerformed'],
            'population_description' => $attributes['populationDescription'] ?? null,
            'sample_description' => $attributes['sampleDescription'] ?? null,
            'result' => $attributes['result'],
            'conclusion' => $attributes['conclusion'],
            'no_evidence_reason' => $attributes['noEvidenceReason'] ?? null,
            'cross_references' => array_values($attributes['crossReferences'] ?? []),
            'change_reason' => $attributes['changeReason'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        if ($evidence->isNotEmpty()) {
            $version->evidence()->attach($evidence->modelKeys());
            $paper->evidence()->syncWithoutDetaching($evidence->modelKeys());
        }

        return $version;
    }

    private function ensureSubmittable(
        WorkingPaper $paper,
        WorkingPaperVersion $version,
    ): void {
        if (! trim((string) $version->population_description)
            || ! trim((string) $version->sample_description)) {
            throw ValidationException::withMessages([
                'populationDescription' => ['Population and sample descriptions are required before submission; use “Not applicable” with an explanation where appropriate.'],
            ]);
        }
        $this->ensureEvidenceExplanation($version->evidence, $version->no_evidence_reason);
        if ($version->evidence->contains(
            fn (AuditEvidence $evidence): bool => ! in_array(
                $evidence->status,
                ['VERIFIED', 'LOCKED'],
                true,
            ),
        )) {
            throw ValidationException::withMessages([
                'evidenceIds' => ['Every cited evidence version must be verified before the Working Paper is submitted.'],
            ]);
        }
        $procedure = $paper->procedure()->with('program')->first();
        if (! $procedure || $procedure->program?->status !== 'ACTIVE'
            || ! $procedure->program?->is_current_revision) {
            throw ValidationException::withMessages([
                'procedureId' => ['The linked procedure must remain in the current active Audit Program.'],
            ]);
        }
    }

    private function nextStatus(string $status, string $action): string
    {
        $transitions = [
            'DRAFT' => ['SUBMIT' => 'SUBMITTED', 'VOID' => 'VOIDED'],
            'SUBMITTED' => ['RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED'],
            'RETURNED_FOR_REVISION' => ['RESUBMIT' => 'RESUBMITTED', 'VOID' => 'VOIDED'],
            'RESUBMITTED' => ['RETURN' => 'RETURNED_FOR_REVISION', 'APPROVE' => 'APPROVED'],
        ];
        $next = $transitions[$status][$action] ?? null;
        if (! $next) {
            throw ValidationException::withMessages([
                'action' => ["{$action} is not allowed while the Working Paper is {$status}."],
            ]);
        }

        return $next;
    }

    private function lockPaper(
        AuditEngagement $engagement,
        WorkingPaper $paper,
        int $lockVersion,
    ): WorkingPaper {
        $locked = WorkingPaper::query()->lockForUpdate()->findOrFail($paper->id);
        if ((int) $locked->audit_engagement_id !== (int) $engagement->id
            || $locked->trashed()) {
            throw ValidationException::withMessages([
                'workingPaper' => ['The Working Paper does not belong to this engagement.'],
            ]);
        }
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This Working Paper changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function nextCode(AuditEngagement $engagement): string
    {
        $sequence = WorkingPaper::query()
            ->withTrashed()
            ->where('audit_engagement_id', $engagement->id)
            ->count() + 1;
        do {
            $code = sprintf('WP-%s-%03d', $engagement->engagement_code, $sequence++);
        } while (WorkingPaper::query()
            ->withTrashed()
            ->where('audit_engagement_id', $engagement->id)
            ->where('working_paper_code', $code)
            ->exists());

        return $code;
    }

    /** @return array<string, mixed> */
    private function versionData(WorkingPaperVersion $version): array
    {
        return [
            'id' => $version->id,
            'versionNumber' => $version->version_number,
            'objective' => $version->objective,
            'procedurePerformed' => $version->procedure_performed,
            'populationDescription' => $version->population_description,
            'sampleDescription' => $version->sample_description,
            'result' => $version->result,
            'conclusion' => $version->conclusion,
            'noEvidenceReason' => $version->no_evidence_reason,
            'crossReferences' => $version->cross_references ?? [],
            'changeReason' => $version->change_reason,
            'createdBy' => $this->user($version->creator),
            'createdAt' => $version->created_at?->toIso8601String(),
            'evidence' => $version->evidence->map(
                fn (AuditEvidence $evidence): array => $this->evidence->data($evidence),
            )->values(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function user(mixed $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'employeeId' => $user->employee_id,
            'name' => $user->name,
            'initials' => $user->initials,
        ] : null;
    }

    private function event(
        Request $request,
        AuditEngagement $engagement,
        WorkingPaper $paper,
        WorkingPaperVersion $version,
        string $action,
        ?string $from,
        ?string $to,
        ?array $before,
        ?array $after,
        ?string $comment = null,
    ): void {
        $this->support->event(
            $request,
            $engagement,
            "WORKING_PAPER_{$action}",
            $from,
            $to,
            $before,
            $after,
            $comment,
            'WORKING_PAPER',
            $paper->id,
            $version->version_number,
            $paper->working_paper_code,
            null,
            $version->evidence->pluck('document_version_id')->all(),
        );
    }

    /** @return array<string, mixed> */
    private function auditValues(
        WorkingPaper $paper,
        WorkingPaperVersion $version,
    ): array {
        return [
            'id' => $paper->id,
            'workingPaperCode' => $paper->working_paper_code,
            'status' => $paper->status,
            'versionNumber' => $version->version_number,
            'procedureId' => $paper->audit_program_procedure_id,
            'evidenceIds' => $version->evidence->modelKeys(),
        ];
    }
}
