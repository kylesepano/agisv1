<?php

namespace App\Services;

use App\Models\AuditEngagement;
use App\Models\AuditEngagementPlan;
use App\Models\AuditProgram;
use App\Models\AuditProgramProcedure;
use App\Models\AemsProcessFlowDocument;
use App\Models\EngagementEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Maintains approved Audit Programs as immutable fieldwork baselines. Definition
 * changes are allowed only in preparation states; changes after approval create
 * a new program revision with copied procedures and a required reason.
 */
class AemsProgramService
{
    public function __construct(
        private readonly AemsAccessService $access,
        private readonly AemsSupport $support,
    ) {}

    /** @return array<string, mixed> */
    public function workspace(AuditEngagement $engagement): array
    {
        $engagement->loadMissing([
            'engagementPlan.latestVersion',
            'planningPackage.latestVersion.processFlows',
            'auditAreas:id,code,name',
            'auditFocuses:id,audit_area_id,code,name',
            'auditType:id,code,label',
            'teamMembers' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->with('user'),
        ]);
        $planningFlows = $engagement->planningPackage?->latestVersion?->processFlows ?? collect();
        $programs = AuditProgram::query()
            ->withTrashed()
            ->where('audit_engagement_id', $engagement->id)
            ->with([
                'preparer',
                'submitter',
                'approver',
                'procedures.assignee',
                'procedures.reviewer',
                'procedures.completer',
                'procedures.waiverApprover',
            ])
            ->orderBy('program_code')
            ->orderByDesc('revision_number')
            ->get();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
                'auditAreas' => $engagement->auditAreas->map(fn ($area): array => [
                    'id' => $area->id,
                    'code' => $area->code,
                    'name' => $area->name,
                ])->values(),
                'auditFocuses' => $engagement->auditFocuses->map(fn ($focus): array => [
                    'id' => $focus->id,
                    'auditAreaId' => $focus->audit_area_id,
                    'code' => $focus->code,
                    'name' => $focus->name,
                ])->values(),
                'auditTypeId' => $engagement->audit_type_id,
                'auditType' => $engagement->auditType ? [
                    'id' => $engagement->auditType->id,
                    'code' => $engagement->auditType->code,
                    'label' => $engagement->auditType->label,
                ] : null,
            ],
            'approvedAep' => $engagement->engagementPlan?->status === 'APPROVED',
            'aep' => $engagement->engagementPlan ? [
                'id' => $engagement->engagementPlan->id,
                'planCode' => $engagement->engagementPlan->plan_code,
                'status' => $engagement->engagementPlan->status,
                'versionNumber' => $engagement->engagementPlan->current_version_number,
                'objectives' => $engagement->engagementPlan->latestVersion?->objectives,
            ] : null,
            'planningProcessFlows' => $planningFlows->map(fn (AemsProcessFlowDocument $flow): array => [
                'id' => $flow->id,
                'code' => $flow->flow_code,
                'title' => $flow->title,
                'auditAreaId' => $flow->audit_area_id,
                'auditFocusId' => $flow->audit_focus_id,
                'sequence' => $flow->sequence,
            ])->values(),
            'programs' => $programs->map(
                fn (AuditProgram $program): array => $this->program($program, $engagement),
            )->values(),
            'team' => $engagement->teamMembers->map(fn ($member): array => [
                'assignmentId' => $member->id,
                'role' => $member->assignment_role_code,
                'plannedPersonDays' => (float) $member->planned_person_days,
                'user' => $this->user($member->user),
            ])->values(),
        ];
    }

    /** @param array<string, mixed> $attributes */
    public function create(
        Request $request,
        AuditEngagement $engagement,
        array $attributes,
    ): AuditProgram {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.program.manage',
        );

        return DB::transaction(function () use ($request, $engagement, $attributes): AuditProgram {
            $lockedEngagement = AuditEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $this->ensurePlanningPhase($lockedEngagement);
            $this->validateEngagementSelections($lockedEngagement, $attributes);
            $aep = $this->approvedAep($lockedEngagement);
            $program = AuditProgram::query()->create([
                'audit_engagement_id' => $lockedEngagement->id,
                'audit_engagement_plan_id' => $aep->id,
                'program_code' => $this->programCode($lockedEngagement),
                'title' => $attributes['title'],
                'objective' => $attributes['objective'],
                'audit_area_id' => $attributes['auditAreaId'] ?? null,
                'audit_type_id' => $attributes['auditTypeId'] ?? null,
                'audit_period_start' => $attributes['auditPeriodStart'] ?? null,
                'audit_period_end' => $attributes['auditPeriodEnd'] ?? null,
                'audit_criteria' => $attributes['auditCriteria'] ?? null,
                'risk_statement_set' => $attributes['riskStatementSet'] ?? [],
                'sampling_approach' => $attributes['samplingApproach'] ?? null,
                'planned_working_paper_requirements' => $attributes['plannedWorkingPaperRequirements'] ?? [],
                'status' => 'DRAFT',
                'revision_number' => 0,
                'is_current_revision' => true,
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
            ]);
            $snapshot = $this->programSnapshot($program);
            $this->event($request, $lockedEngagement, $program, 'CREATE', null, 'DRAFT', null, $snapshot);
            $this->support->audit(
                $request,
                'aems.program.created',
                $lockedEngagement,
                null,
                $snapshot,
                ['programId' => $program->id, 'programCode' => $program->program_code],
            );

            return $program->fresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        array $attributes,
    ): AuditProgram {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.program.manage',
        );

        return DB::transaction(function () use ($request, $engagement, $program, $attributes): AuditProgram {
            $this->ensurePlanningPhase($engagement);
            $locked = $this->lockProgram($engagement, $program, (int) $attributes['lockVersion']);
            $this->ensureDefinitionEditable($locked);
            $this->validateEngagementSelections($engagement, $attributes);
            $before = $this->programSnapshot($locked);
            $locked->update([
                'title' => $attributes['title'],
                'objective' => $attributes['objective'],
                'audit_area_id' => $attributes['auditAreaId'] ?? null,
                'audit_type_id' => $attributes['auditTypeId'] ?? null,
                'audit_period_start' => $attributes['auditPeriodStart'] ?? null,
                'audit_period_end' => $attributes['auditPeriodEnd'] ?? null,
                'audit_criteria' => $attributes['auditCriteria'] ?? null,
                'risk_statement_set' => $attributes['riskStatementSet'] ?? [],
                'sampling_approach' => $attributes['samplingApproach'] ?? null,
                'planned_working_paper_requirements' => $attributes['plannedWorkingPaperRequirements'] ?? [],
                'prepared_by' => $request->user()->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $after = $this->programSnapshot($locked->fresh());
            $this->event($request, $engagement, $locked, 'UPDATE', $locked->status, $locked->status, $before, $after);
            $this->support->audit(
                $request,
                'aems.program.updated',
                $engagement,
                $before,
                $after,
                ['programId' => $locked->id, 'programCode' => $locked->program_code],
            );

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function addProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        array $attributes,
    ): AuditProgramProcedure {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.program.manage',
        );

        return DB::transaction(function () use ($request, $engagement, $program, $attributes): AuditProgramProcedure {
            $this->ensurePlanningPhase($engagement);
            $locked = $this->lockProgram($engagement, $program, (int) $attributes['programLockVersion']);
            $this->ensureDefinitionEditable($locked);
            $this->ensureAssignee($engagement, (int) $attributes['assignedTo']);
            $this->validateProcedureSelections($engagement, $attributes);
            $procedure = AuditProgramProcedure::query()->create([
                'audit_program_id' => $locked->id,
                'procedure_code' => $attributes['procedureCode'],
                'sequence_number' => $attributes['sequenceNumber'],
                'objective' => $attributes['objective'],
                'procedure_description' => $attributes['procedureDescription'],
                'expected_evidence' => $attributes['expectedEvidence'] ?? null,
                'working_paper_reference' => $attributes['workingPaperReference'] ?? null,
                'assigned_to' => $attributes['assignedTo'],
                'target_date' => $attributes['targetDate'],
                'audit_area_id' => $attributes['auditAreaId'] ?? null,
                'audit_focus_id' => $attributes['auditFocusId'] ?? null,
                'process_flow_id' => $attributes['processFlowId'] ?? null,
                'process_name' => $attributes['processName'] ?? null,
                'audit_method' => $attributes['auditMethod'] ?? null,
                'audit_criteria' => $attributes['auditCriteria'] ?? null,
                'planned_person_days' => $attributes['plannedPersonDays'] ?? null,
                'sampling_requirement' => $attributes['samplingRequirement'] ?? [],
                'planned_working_paper_requirement' => $attributes['plannedWorkingPaperRequirement'] ?? [],
                'risk_statement_ids' => $attributes['riskStatementIds'] ?? [],
                'status' => 'NOT_STARTED',
                'lock_version' => 1,
            ]);
            $locked->update(['lock_version' => $locked->lock_version + 1]);
            $snapshot = $this->procedureSnapshot($procedure->fresh(['assignee']));
            $this->event(
                $request,
                $engagement,
                $locked,
                'PROCEDURE_CREATE',
                $locked->status,
                $locked->status,
                null,
                $snapshot,
            );
            $this->support->audit(
                $request,
                'aems.program.procedure_created',
                $engagement,
                null,
                $snapshot,
                ['programId' => $locked->id, 'procedureId' => $procedure->id],
            );

            return $procedure->fresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function updateProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        AuditProgramProcedure $procedure,
        array $attributes,
    ): AuditProgramProcedure {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.program.manage',
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $program,
            $procedure,
            $attributes,
        ): AuditProgramProcedure {
            $this->ensurePlanningPhase($engagement);
            $lockedProgram = $this->lockProgram(
                $engagement,
                $program,
                (int) $attributes['programLockVersion'],
            );
            $this->ensureDefinitionEditable($lockedProgram);
            $locked = $this->lockProcedure($lockedProgram, $procedure, (int) $attributes['lockVersion']);
            $this->ensureAssignee($engagement, (int) $attributes['assignedTo']);
            $this->validateProcedureSelections($engagement, $attributes);
            $before = $this->procedureSnapshot($locked->loadMissing('assignee'));
            $locked->update([
                'procedure_code' => $attributes['procedureCode'],
                'sequence_number' => $attributes['sequenceNumber'],
                'objective' => $attributes['objective'],
                'procedure_description' => $attributes['procedureDescription'],
                'expected_evidence' => $attributes['expectedEvidence'] ?? null,
                'working_paper_reference' => $attributes['workingPaperReference'] ?? null,
                'assigned_to' => $attributes['assignedTo'],
                'target_date' => $attributes['targetDate'],
                'audit_area_id' => $attributes['auditAreaId'] ?? null,
                'audit_focus_id' => $attributes['auditFocusId'] ?? null,
                'process_flow_id' => $attributes['processFlowId'] ?? null,
                'process_name' => $attributes['processName'] ?? null,
                'audit_method' => $attributes['auditMethod'] ?? null,
                'audit_criteria' => $attributes['auditCriteria'] ?? null,
                'planned_person_days' => $attributes['plannedPersonDays'] ?? null,
                'sampling_requirement' => $attributes['samplingRequirement'] ?? [],
                'planned_working_paper_requirement' => $attributes['plannedWorkingPaperRequirement'] ?? [],
                'risk_statement_ids' => $attributes['riskStatementIds'] ?? [],
                'lock_version' => $locked->lock_version + 1,
            ]);
            $lockedProgram->update(['lock_version' => $lockedProgram->lock_version + 1]);
            $after = $this->procedureSnapshot($locked->fresh(['assignee']));
            $this->event(
                $request,
                $engagement,
                $lockedProgram,
                'PROCEDURE_UPDATE',
                $lockedProgram->status,
                $lockedProgram->status,
                $before,
                $after,
            );
            $this->support->audit(
                $request,
                'aems.program.procedure_updated',
                $engagement,
                $before,
                $after,
                ['programId' => $lockedProgram->id, 'procedureId' => $locked->id],
            );

            return $locked->fresh();
        });
    }

    public function removeProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        AuditProgramProcedure $procedure,
        int $programLockVersion,
        int $lockVersion,
    ): void {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.program.manage',
        );
        DB::transaction(function () use (
            $request,
            $engagement,
            $program,
            $procedure,
            $programLockVersion,
            $lockVersion,
        ): void {
            $this->ensurePlanningPhase($engagement);
            $lockedProgram = $this->lockProgram($engagement, $program, $programLockVersion);
            $this->ensureDefinitionEditable($lockedProgram);
            $locked = $this->lockProcedure($lockedProgram, $procedure, $lockVersion);
            $before = $this->procedureSnapshot($locked->loadMissing('assignee'));
            $locked->delete();
            $lockedProgram->update(['lock_version' => $lockedProgram->lock_version + 1]);
            $this->event(
                $request,
                $engagement,
                $lockedProgram,
                'PROCEDURE_ARCHIVE',
                $lockedProgram->status,
                $lockedProgram->status,
                $before,
                null,
            );
            $this->support->audit(
                $request,
                'aems.program.procedure_archived',
                $engagement,
                $before,
                null,
                ['programId' => $lockedProgram->id, 'procedureId' => $locked->id],
            );
        });
    }

    public function transition(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        string $action,
        int $lockVersion,
        ?string $comment,
    ): AuditProgram {
        $permissions = [
            'SUBMIT' => 'aems.program.manage',
            'RESUBMIT' => 'aems.program.manage',
            'REVIEW' => 'aems.program.review',
            'RETURN' => 'aems.program.review',
            'APPROVE' => 'aems.program.approve',
            'START' => 'aems.program.approve',
            'COMPLETE' => 'aems.program.manage',
        ];
        if (! isset($permissions[$action])) {
            throw ValidationException::withMessages(['action' => ['Unsupported Audit Program action.']]);
        }
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            $permissions[$action],
            in_array($action, ['REVIEW', 'RETURN', 'APPROVE'], true)
                ? $program->prepared_by : null,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $program,
            $action,
            $lockVersion,
            $comment,
        ): AuditProgram {
            if (! in_array($action, ['START', 'COMPLETE'], true)) {
                $this->ensurePlanningPhase($engagement);
            }
            $locked = $this->lockProgram($engagement, $program, $lockVersion);
            $from = $locked->status;
            $to = $this->nextStatus($locked, $action);
            if ($action === 'RETURN' && mb_strlen(trim((string) $comment)) < 5) {
                throw ValidationException::withMessages(['comment' => ['A clear return instruction is required.']]);
            }
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $this->ensureSubmittable($locked);
            }
            if ($action === 'APPROVE') {
                $mayUseSingleCiasAuthority = $this->access->mayUseSingleCiasHeadReviewException(
                    $request->user(),
                    'aems.program.approve',
                );
                $reviewed = EngagementEvent::query()
                    ->where('audit_engagement_id', $engagement->id)
                    ->where('subject_type', 'AUDIT_PROGRAM')
                    ->where('subject_id', $locked->id)
                    ->where('subject_version', $locked->revision_number)
                    ->where('action', 'PROGRAM_REVIEW')
                    ->when(
                        ! $mayUseSingleCiasAuthority,
                        fn ($query) => $query->where('actor_id', '<>', $locked->prepared_by),
                    )
                    ->exists();
                if (! $reviewed) {
                    throw ValidationException::withMessages([
                        'action' => ['This program revision must be independently reviewed before approval.'],
                    ]);
                }
            }
            if ($action === 'COMPLETE') {
                $incomplete = $locked->procedures()
                    ->whereNotIn('status', ['COMPLETED', 'WAIVED'])
                    ->exists();
                $unreviewed = $locked->procedures()
                    ->where('status', 'COMPLETED')
                    ->whereNull('reviewer_result')
                    ->exists();
                $withoutApprovedWorkingPaper = $locked->procedures()
                    ->where('status', 'COMPLETED')
                    ->whereDoesntHave(
                        'workingPapers',
                        fn ($papers) => $papers->where('status', 'APPROVED'),
                    )
                    ->exists();
                $workingPaperInReview = $locked->procedures()
                    ->whereHas(
                        'workingPapers',
                        fn ($papers) => $papers->whereNotIn('status', ['APPROVED', 'VOIDED']),
                    )
                    ->exists();
                if ($incomplete || $unreviewed
                    || $withoutApprovedWorkingPaper || $workingPaperInReview) {
                    throw ValidationException::withMessages([
                        'procedures' => ['Every procedure must be completed or waived, completed procedures must have a reviewer result and an approved Working Paper, and no Working Paper may remain in review.'],
                    ]);
                }
            }

            $changes = [
                'status' => $action === 'REVIEW' ? $from : $to,
                'lock_version' => $locked->lock_version + 1,
            ];
            if (in_array($action, ['SUBMIT', 'RESUBMIT'], true)) {
                $changes['submitted_by'] = $request->user()->id;
                $changes['submitted_at'] = now();
            }
            if ($action === 'APPROVE') {
                $changes['approved_by'] = $request->user()->id;
                $changes['approved_at'] = now();
            }
            if ($action === 'START') {
                $changes['activated_at'] = now();
            }
            if ($action === 'COMPLETE') {
                $changes['completed_at'] = now();
            }
            $locked->update($changes);
            $this->event(
                $request,
                $engagement,
                $locked,
                $action,
                $from,
                $to,
                ['status' => $from],
                ['status' => $to, 'revisionNumber' => $locked->revision_number],
                $comment,
            );
            $this->support->audit(
                $request,
                'aems.program.'.str($action)->lower(),
                $engagement,
                ['status' => $from],
                ['status' => $to, 'revisionNumber' => $locked->revision_number],
                ['programId' => $locked->id, 'programCode' => $locked->program_code, 'comment' => $comment],
            );

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function progressProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        AuditProgramProcedure $procedure,
        array $attributes,
    ): AuditProgramProcedure {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.program.manage',
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $program,
            $procedure,
            $attributes,
        ): AuditProgramProcedure {
            $lockedProgram = $this->lockProgram($engagement, $program, (int) $attributes['programLockVersion']);
            if ($lockedProgram->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'status' => ['Procedure progress can be recorded only against the active approved baseline.'],
                ]);
            }
            $locked = $this->lockProcedure($lockedProgram, $procedure, (int) $attributes['lockVersion']);
            $this->ensureProgressActor($request, $engagement, $locked, $attributes['status']);
            if ($attributes['status'] === 'COMPLETED'
                && ! trim((string) ($attributes['workingPaperReference'] ?? $locked->working_paper_reference))) {
                throw ValidationException::withMessages([
                    'workingPaperReference' => ['A working-paper reference is required to complete a procedure.'],
                ]);
            }
            if ($attributes['status'] === 'COMPLETED'
                && ! $locked->fieldworkRecords()->where('status', 'FINALIZED')->exists()) {
                throw ValidationException::withMessages([
                    'fieldwork' => ['Every completed procedure must have at least one finalized Fieldwork Record.'],
                ]);
            }
            if ($attributes['status'] === 'WAIVED'
                && mb_strlen(trim((string) ($attributes['waiverReason'] ?? ''))) < 5) {
                throw ValidationException::withMessages([
                    'waiverReason' => ['A documented waiver reason is required.'],
                ]);
            }
            $before = $this->procedureSnapshot($locked->loadMissing('assignee'));
            $changes = [
                'status' => $attributes['status'],
                'working_paper_reference' => $attributes['workingPaperReference']
                    ?? $locked->working_paper_reference,
                'fieldwork_results' => array_key_exists('results', $attributes)
                    ? $attributes['results'] : $locked->fieldwork_results,
                'fieldwork_conclusion' => array_key_exists('conclusion', $attributes)
                    ? $attributes['conclusion'] : $locked->fieldwork_conclusion,
                'fieldwork_review_state' => array_key_exists('reviewState', $attributes)
                    ? $attributes['reviewState'] : $locked->fieldwork_review_state,
                'related_tasks' => array_key_exists('relatedTasks', $attributes)
                    ? $attributes['relatedTasks'] : $locked->related_tasks,
                'related_records' => array_key_exists('relatedRecords', $attributes)
                    ? $attributes['relatedRecords'] : $locked->related_records,
                'lock_version' => $locked->lock_version + 1,
            ];
            if ($attributes['status'] === 'COMPLETED') {
                $changes['completed_at'] = now();
                $changes['completed_by'] = $request->user()->id;
                $changes['waived_at'] = null;
                $changes['waived_by'] = null;
                $changes['waiver_reason'] = null;
            } elseif ($attributes['status'] === 'WAIVED') {
                $changes['waived_at'] = now();
                $changes['waived_by'] = $request->user()->id;
                $changes['waiver_reason'] = $attributes['waiverReason'];
                $changes['completed_at'] = null;
                $changes['completed_by'] = null;
            } else {
                $changes['completed_at'] = null;
                $changes['completed_by'] = null;
                $changes['waived_at'] = null;
                $changes['waived_by'] = null;
                $changes['waiver_reason'] = null;
            }
            $locked->update($changes);
            $lockedProgram->update(['lock_version' => $lockedProgram->lock_version + 1]);
            $after = $this->procedureSnapshot($locked->fresh(['assignee', 'completer', 'waiverApprover']));
            $this->event(
                $request,
                $engagement,
                $lockedProgram,
                'PROCEDURE_PROGRESS',
                $lockedProgram->status,
                $lockedProgram->status,
                $before,
                $after,
                $attributes['comment'] ?? null,
            );
            $this->support->audit(
                $request,
                'aems.program.procedure_progress',
                $engagement,
                $before,
                $after,
                ['programId' => $lockedProgram->id, 'procedureId' => $locked->id],
            );

            return $locked->fresh();
        });
    }

    /** @param array<string, mixed> $attributes */
    public function reviewProcedure(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        AuditProgramProcedure $procedure,
        array $attributes,
    ): AuditProgramProcedure {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.program.review',
            $procedure->assigned_to,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $program,
            $procedure,
            $attributes,
        ): AuditProgramProcedure {
            $lockedProgram = $this->lockProgram($engagement, $program, (int) $attributes['programLockVersion']);
            if (! in_array($lockedProgram->status, ['ACTIVE', 'COMPLETED'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Procedure review is available after the approved program is activated.'],
                ]);
            }
            $locked = $this->lockProcedure($lockedProgram, $procedure, (int) $attributes['lockVersion']);
            if ($locked->status !== 'COMPLETED') {
                throw ValidationException::withMessages([
                    'status' => ['Only a completed procedure can receive a reviewer result.'],
                ]);
            }
            $before = $this->procedureSnapshot($locked->loadMissing('reviewer'));
            $locked->update([
                'reviewer_result' => $attributes['reviewerResult'],
                'reviewer_comments' => $attributes['reviewerComments'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'lock_version' => $locked->lock_version + 1,
            ]);
            $lockedProgram->update(['lock_version' => $lockedProgram->lock_version + 1]);
            $after = $this->procedureSnapshot($locked->fresh(['assignee', 'reviewer']));
            $this->event(
                $request,
                $engagement,
                $lockedProgram,
                'PROCEDURE_REVIEW',
                $lockedProgram->status,
                $lockedProgram->status,
                $before,
                $after,
                $attributes['reviewerComments'] ?? null,
            );
            $this->support->audit(
                $request,
                'aems.program.procedure_reviewed',
                $engagement,
                $before,
                $after,
                ['programId' => $lockedProgram->id, 'procedureId' => $locked->id],
            );

            return $locked->fresh();
        });
    }

    public function revise(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        int $lockVersion,
        string $reason,
    ): AuditProgram {
        $this->access->authorizeEngagementAction(
            $request->user(),
            $engagement,
            'aems.program.approve',
            $program->prepared_by,
        );

        return DB::transaction(function () use (
            $request,
            $engagement,
            $program,
            $lockVersion,
            $reason,
        ): AuditProgram {
            $this->ensurePlanningPhase($engagement);
            $locked = $this->lockProgram($engagement, $program, $lockVersion);
            if (! in_array($locked->status, ['APPROVED', 'ACTIVE'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only an approved or active program can start a formal revision.'],
                ]);
            }
            $locked->load('procedures');
            $sourceStatus = $locked->status;
            $locked->update([
                'status' => 'SUPERSEDED',
                'is_current_revision' => false,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $revision = AuditProgram::query()->create([
                'audit_engagement_id' => $locked->audit_engagement_id,
                'audit_engagement_plan_id' => $locked->audit_engagement_plan_id,
                'program_code' => $locked->program_code,
                'title' => $locked->title,
                'objective' => $locked->objective,
                'status' => 'DRAFT',
                'revision_number' => $locked->revision_number + 1,
                'supersedes_program_id' => $locked->id,
                'revision_reason' => $reason,
                // Insert outside the current-revision partial unique index,
                // then promote after the source has been superseded.
                'is_current_revision' => false,
                'prepared_by' => $request->user()->id,
                'lock_version' => 1,
                'is_active' => true,
                'audit_area_id' => $locked->audit_area_id,
                'audit_type_id' => $locked->audit_type_id,
                'audit_period_start' => $locked->audit_period_start,
                'audit_period_end' => $locked->audit_period_end,
                'audit_criteria' => $locked->audit_criteria,
                'risk_statement_set' => $locked->risk_statement_set,
                'sampling_approach' => $locked->sampling_approach,
                'planned_working_paper_requirements' => $locked->planned_working_paper_requirements,
            ]);
            $revision->update(['is_current_revision' => true]);
            foreach ($locked->procedures as $procedure) {
                AuditProgramProcedure::query()->create([
                    ...$procedure->only([
                        'procedure_code',
                        'sequence_number',
                        'objective',
                        'procedure_description',
                        'expected_evidence',
                        'working_paper_reference',
                        'assigned_to',
                        'target_date',
                        'audit_area_id',
                        'audit_focus_id',
                        'process_flow_id',
                        'process_name',
                        'audit_method',
                        'audit_criteria',
                        'planned_person_days',
                        'sampling_requirement',
                        'planned_working_paper_requirement',
                        'risk_statement_ids',
                    ]),
                    'audit_program_id' => $revision->id,
                    'status' => 'NOT_STARTED',
                    'lock_version' => 1,
                ]);
            }
            $this->event(
                $request,
                $engagement,
                $revision,
                'REVISE',
                $sourceStatus,
                'DRAFT',
                ['programId' => $locked->id, 'revisionNumber' => $locked->revision_number],
                ['programId' => $revision->id, 'revisionNumber' => $revision->revision_number],
                $reason,
            );
            $this->support->audit(
                $request,
                'aems.program.revision_started',
                $engagement,
                ['programId' => $locked->id, 'revisionNumber' => $locked->revision_number],
                ['programId' => $revision->id, 'revisionNumber' => $revision->revision_number],
                ['programCode' => $revision->program_code, 'reason' => $reason],
            );

            return $revision->fresh();
        });
    }

    private function approvedAep(AuditEngagement $engagement): AuditEngagementPlan
    {
        $aep = $engagement->engagementPlan()->where('status', 'APPROVED')->first();
        if (! $aep) {
            throw ValidationException::withMessages([
                'engagement' => ['An approved Audit Engagement Plan is required before creating an Audit Program.'],
            ]);
        }

        return $aep;
    }

    /**
     * Program definitions inherit their scope from the engagement. Rejecting
     * unrelated registry IDs here prevents a caller from bypassing the
     * engagement-scoped selectors in the React workspace.
     *
     * @param array<string,mixed> $attributes
     */
    private function validateEngagementSelections(AuditEngagement $engagement, array $attributes): void
    {
        $areaId = filled($attributes['auditAreaId'] ?? null)
            ? (int) $attributes['auditAreaId']
            : null;
        $typeId = filled($attributes['auditTypeId'] ?? null)
            ? (int) $attributes['auditTypeId']
            : null;
        $selectedAreaIds = $engagement->auditAreas()->pluck('audit_areas.id');

        if ($selectedAreaIds->isNotEmpty() && $areaId === null) {
            throw ValidationException::withMessages([
                'auditAreaId' => ['Select an audit area from the engagement scope.'],
            ]);
        }
        if ($areaId !== null && ! $selectedAreaIds->contains($areaId)) {
            throw ValidationException::withMessages([
                'auditAreaId' => ['The audit area must be selected in the engagement scope.'],
            ]);
        }

        if ($engagement->audit_type_id !== null && $typeId === null) {
            throw ValidationException::withMessages([
                'auditTypeId' => ['Select the audit type configured on the engagement.'],
            ]);
        }
        if ($typeId !== null && (int) $engagement->audit_type_id !== $typeId) {
            throw ValidationException::withMessages([
                'auditTypeId' => ['The audit type must match the engagement configuration.'],
            ]);
        }
    }

    /** @param array<string,mixed> $attributes */
    private function validateProcedureSelections(AuditEngagement $engagement, array $attributes): void
    {
        $areaId = filled($attributes['auditAreaId'] ?? null)
            ? (int) $attributes['auditAreaId']
            : null;
        $focusId = filled($attributes['auditFocusId'] ?? null)
            ? (int) $attributes['auditFocusId']
            : null;
        $selectedAreaIds = $engagement->auditAreas()->pluck('audit_areas.id');
        $selectedFocuses = $engagement->auditFocuses()->get(['audit_focuses.id', 'audit_focuses.audit_area_id']);

        if ($areaId !== null && ! $selectedAreaIds->contains($areaId)) {
            throw ValidationException::withMessages([
                'auditAreaId' => ['The procedure audit area must be selected in the engagement scope.'],
            ]);
        }
        if ($focusId !== null && ! $selectedFocuses->contains('id', $focusId)) {
            throw ValidationException::withMessages([
                'auditFocusId' => ['The procedure audit focus must be selected in the engagement scope.'],
            ]);
        }
        if ($focusId !== null && $areaId !== null) {
            $focus = $selectedFocuses->firstWhere('id', $focusId);
            if ($focus && (int) $focus->audit_area_id !== $areaId) {
                throw ValidationException::withMessages([
                    'auditFocusId' => ['The selected audit focus does not belong to the selected audit area.'],
                ]);
            }
        }

        $processFlowId = filled($attributes['processFlowId'] ?? null)
            ? (int) $attributes['processFlowId']
            : null;
        if ($processFlowId !== null) {
            $flow = AemsProcessFlowDocument::query()
                ->whereKey($processFlowId)
                ->whereHas('version.package', fn ($query) => $query->where('audit_engagement_id', $engagement->id))
                ->whereHas('version', fn ($query) => $query->whereKey(
                    $engagement->planningPackage?->latestVersion?->id,
                ))
                ->first();

            if (! $flow) {
                throw ValidationException::withMessages([
                    'processFlowId' => ['The process flow must be selected from the current Planning Package version.'],
                ]);
            }
            if ($areaId !== null && $flow->audit_area_id !== null && (int) $flow->audit_area_id !== $areaId) {
                throw ValidationException::withMessages([
                    'processFlowId' => ['The selected process flow does not belong to the selected audit area.'],
                ]);
            }
            if ($focusId !== null && $flow->audit_focus_id !== null && (int) $flow->audit_focus_id !== $focusId) {
                throw ValidationException::withMessages([
                    'processFlowId' => ['The selected process flow does not belong to the selected audit focus.'],
                ]);
            }
        }
    }

    private function lockProgram(
        AuditEngagement $engagement,
        AuditProgram $program,
        int $lockVersion,
    ): AuditProgram {
        $locked = AuditProgram::query()->lockForUpdate()->findOrFail($program->id);
        if ((int) $locked->audit_engagement_id !== (int) $engagement->id || $locked->trashed()) {
            throw ValidationException::withMessages(['program' => ['The Audit Program does not belong to this engagement.']]);
        }
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This program changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function lockProcedure(
        AuditProgram $program,
        AuditProgramProcedure $procedure,
        int $lockVersion,
    ): AuditProgramProcedure {
        $locked = AuditProgramProcedure::query()->lockForUpdate()->findOrFail($procedure->id);
        if ((int) $locked->audit_program_id !== (int) $program->id || $locked->trashed()) {
            throw ValidationException::withMessages(['procedure' => ['The procedure does not belong to this program revision.']]);
        }
        if ($locked->lock_version !== $lockVersion) {
            throw ValidationException::withMessages([
                'lockVersion' => ['This procedure changed in another session. Refresh before continuing.'],
            ]);
        }

        return $locked;
    }

    private function ensureDefinitionEditable(AuditProgram $program): void
    {
        if (! $program->is_current_revision
            || ! in_array($program->status, ['DRAFT', 'RETURNED_FOR_REVISION'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Approved program baselines are immutable. Create a documented revision to change definitions.'],
            ]);
        }
    }

    private function ensurePlanningPhase(AuditEngagement $engagement): void
    {
        if ($engagement->status !== 'ENGAGEMENT_PLANNING') {
            throw ValidationException::withMessages([
                'engagement' => ['Planning Workspace is locked until the engagement reaches ENGAGEMENT_PLANNING after the issued AEO is acknowledged by the auditee office.'],
            ]);
        }
    }

    private function ensureAssignee(AuditEngagement $engagement, int $userId): void
    {
        $assigned = $engagement->teamMembers()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->whereIn('assignment_role_code', ['TEAM_LEADER', 'AUDITOR'])
            ->exists();
        if (! $assigned) {
            throw ValidationException::withMessages([
                'assignedTo' => ['The responsible auditor must be an active Team Leader or Auditor on this engagement.'],
            ]);
        }
    }

    private function ensureSubmittable(AuditProgram $program): void
    {
        $procedures = $program->procedures()->get();
        if ($procedures->isEmpty()) {
            throw ValidationException::withMessages([
                'procedures' => ['Add at least one audit procedure before submission.'],
            ]);
        }
        if ($procedures->contains(fn ($procedure): bool => ! $procedure->assigned_to
            || ! $procedure->target_date
            || ! trim((string) $procedure->expected_evidence))) {
            throw ValidationException::withMessages([
                'procedures' => ['Every procedure requires an assigned auditor, target date, and expected evidence.'],
            ]);
        }
    }

    private function ensureProgressActor(
        Request $request,
        AuditEngagement $engagement,
        AuditProgramProcedure $procedure,
        string $status,
    ): void {
        $role = $engagement->teamMembers()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->value('assignment_role_code');
        if ($status === 'WAIVED' && $role !== 'SUPERVISOR' && ! $request->user()->hasPermission('aems.program.approve')) {
            throw ValidationException::withMessages([
                'status' => ['Only the Supervisor or a user with program approval permission may waive a procedure.'],
            ]);
        }
        if ($status !== 'WAIVED'
            && (int) $procedure->assigned_to !== (int) $request->user()->id
            && ! in_array($role, ['SUPERVISOR', 'TEAM_LEADER'], true)
            && ! $request->user()->hasPermission('aems.program.manage')) {
            throw ValidationException::withMessages([
                'status' => ['Only the responsible auditor, Team Leader, or Supervisor may update progress.'],
            ]);
        }
    }

    private function nextStatus(AuditProgram $program, string $action): string
    {
        $transitions = [
            'DRAFT' => ['SUBMIT' => 'PENDING_REVIEW'],
            'PENDING_REVIEW' => [
                'REVIEW' => 'PENDING_REVIEW',
                'RETURN' => 'RETURNED_FOR_REVISION',
                'APPROVE' => 'APPROVED',
            ],
            'RETURNED_FOR_REVISION' => ['RESUBMIT' => 'RESUBMITTED'],
            'RESUBMITTED' => [
                'REVIEW' => 'RESUBMITTED',
                'RETURN' => 'RETURNED_FOR_REVISION',
                'APPROVE' => 'APPROVED',
            ],
            'APPROVED' => ['START' => 'ACTIVE'],
            'ACTIVE' => ['COMPLETE' => 'COMPLETED'],
        ];
        $next = $transitions[$program->status][$action] ?? null;
        if (! $next) {
            throw ValidationException::withMessages([
                'action' => ["{$action} is not allowed while the program is {$program->status}."],
            ]);
        }

        return $next;
    }

    private function programCode(AuditEngagement $engagement): string
    {
        $sequence = AuditProgram::withTrashed()
            ->where('audit_engagement_id', $engagement->id)
            ->distinct('program_code')
            ->count('program_code') + 1;

        do {
            $code = sprintf('AP-%s-%02d', $engagement->engagement_code, $sequence++);
        } while (AuditProgram::withTrashed()
            ->where('audit_engagement_id', $engagement->id)
            ->where('program_code', $code)
            ->exists());

        return $code;
    }

    /** @return array<string, mixed> */
    private function program(AuditProgram $program, AuditEngagement $engagement): array
    {
        $events = EngagementEvent::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('subject_type', 'AUDIT_PROGRAM')
            ->where('subject_id', $program->id)
            ->with('actor')
            ->latest('created_at')
            ->get();

        return [
            ...$this->programSnapshot($program),
            'preparedBy' => $this->user($program->preparer),
            'submittedBy' => $this->user($program->submitter),
            'submittedAt' => $program->submitted_at?->toISOString(),
            'approvedBy' => $this->user($program->approver),
            'approvedAt' => $program->approved_at?->toISOString(),
            'activatedAt' => $program->activated_at?->toISOString(),
            'completedAt' => $program->completed_at?->toISOString(),
            'procedures' => $program->procedures->map(
                fn (AuditProgramProcedure $procedure): array => $this->procedureSnapshot($procedure),
            )->values(),
            'events' => $events->map(fn (EngagementEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'fromStatus' => $event->from_status,
                'toStatus' => $event->to_status,
                'comment' => $event->comment,
                'createdAt' => $event->created_at?->toISOString(),
                'actor' => $this->user($event->actor),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function programSnapshot(AuditProgram $program): array
    {
        return [
            'id' => $program->id,
            'programCode' => $program->program_code,
            'title' => $program->title,
            'objective' => $program->objective,
            'auditAreaId' => $program->audit_area_id,
            'auditTypeId' => $program->audit_type_id,
            'auditPeriodStart' => $program->audit_period_start?->toDateString(),
            'auditPeriodEnd' => $program->audit_period_end?->toDateString(),
            'auditCriteria' => $program->audit_criteria,
            'riskStatementSet' => $program->risk_statement_set ?? [],
            'samplingApproach' => $program->sampling_approach,
            'plannedWorkingPaperRequirements' => $program->planned_working_paper_requirements ?? [],
            'status' => $program->status,
            'revisionNumber' => $program->revision_number,
            'supersedesProgramId' => $program->supersedes_program_id,
            'revisionReason' => $program->revision_reason,
            'isCurrentRevision' => $program->is_current_revision,
            'lockVersion' => $program->lock_version,
            'isArchived' => $program->trashed(),
        ];
    }

    /** @return array<string, mixed> */
    private function procedureSnapshot(AuditProgramProcedure $procedure): array
    {
        return [
            'id' => $procedure->id,
            'procedureCode' => $procedure->procedure_code,
            'sequenceNumber' => $procedure->sequence_number,
            'objective' => $procedure->objective,
            'procedureDescription' => $procedure->procedure_description,
            'expectedEvidence' => $procedure->expected_evidence,
            'workingPaperReference' => $procedure->working_paper_reference,
            'assignedTo' => $procedure->assigned_to,
            'assignee' => $this->user($procedure->assignee),
            'targetDate' => $procedure->target_date?->toDateString(),
            'auditAreaId' => $procedure->audit_area_id,
            'auditFocusId' => $procedure->audit_focus_id,
            'processFlowId' => $procedure->process_flow_id,
            'processName' => $procedure->process_name,
            'auditMethod' => $procedure->audit_method,
            'auditCriteria' => $procedure->audit_criteria,
            'plannedPersonDays' => $procedure->planned_person_days,
            'samplingRequirement' => $procedure->sampling_requirement ?? [],
            'plannedWorkingPaperRequirement' => $procedure->planned_working_paper_requirement ?? [],
            'riskStatementIds' => $procedure->risk_statement_ids ?? [],
            'status' => $procedure->status,
            'reviewerResult' => $procedure->reviewer_result,
            'reviewerComments' => $procedure->reviewer_comments,
            'reviewer' => $this->user($procedure->reviewer),
            'reviewedAt' => $procedure->reviewed_at?->toISOString(),
            'completedBy' => $this->user($procedure->completer),
            'completedAt' => $procedure->completed_at?->toISOString(),
            'waivedBy' => $this->user($procedure->waiverApprover),
            'waivedAt' => $procedure->waived_at?->toISOString(),
            'waiverReason' => $procedure->waiver_reason,
            'fieldworkStatus' => $procedure->fieldwork_status,
            'fieldworkResults' => $procedure->fieldwork_results,
            'fieldworkConclusion' => $procedure->fieldwork_conclusion,
            'fieldworkReviewState' => $procedure->fieldwork_review_state,
            'relatedTasks' => $procedure->related_tasks ?? [],
            'relatedRecords' => $procedure->related_records ?? [],
            'fieldworkCompletedAt' => $procedure->fieldwork_completed_at?->toIso8601String(),
            'fieldworkCompletedBy' => $this->user($procedure->fieldworkCompletedBy),
            'lockVersion' => $procedure->lock_version,
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

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    private function event(
        Request $request,
        AuditEngagement $engagement,
        AuditProgram $program,
        string $action,
        ?string $from,
        ?string $to,
        ?array $oldValues,
        ?array $newValues,
        ?string $comment = null,
    ): void {
        $this->support->event(
            $request,
            $engagement,
            'PROGRAM_'.$action,
            $from,
            $to,
            $oldValues,
            $newValues,
            $comment,
            'AUDIT_PROGRAM',
            $program->id,
            $program->revision_number,
            $program->program_code,
        );
    }
}
