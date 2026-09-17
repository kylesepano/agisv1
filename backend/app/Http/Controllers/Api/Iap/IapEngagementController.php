<?php

namespace App\Http\Controllers\Api\Iap;

use App\Http\Controllers\Controller;
use App\Http\Requests\Iap\IapEngagementRequest;
use App\Http\Requests\Iap\IapTeamRequest;
use App\Models\AuditFocus;
use App\Models\IapEngagementTeamMember;
use App\Models\IapPlanEngagement;
use App\Models\IapPrioritizationItem;
use App\Models\IapRiskAssessment;
use App\Models\InternalAuditPlan;
use App\Models\User;
use App\Services\IapPlanGuard;
use App\Services\IapSupport;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages proposed Annual Plan engagements and their team and skill requirements.
 */
class IapEngagementController extends Controller
{
    public function __construct(
        private readonly IapPlanGuard $guard,
        private readonly IapSupport $support,
        private readonly NotificationService $notifications,
    ) {}

    public function store(IapEngagementRequest $request, InternalAuditPlan $plan): JsonResponse
    {
        $this->guard->assertEditable($request->user(), $plan);
        $source = $this->prioritizationSource($request, $plan);
        $this->validateDomain($request, $plan, $source);

        $engagement = DB::transaction(function () use ($request, $plan, $source): IapPlanEngagement {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $this->guard->assertEditable($request->user(), $lockedPlan);
            if ($request->filled('lockVersion')) {
                $this->guard->assertLockVersion($lockedPlan, (int) $request->validated('lockVersion'));
            }

            $existing = IapPlanEngagement::withTrashed()
                ->where('plan_id', $plan->id)
                ->where('engagement_code', $request->validated('engagementCode'))
                ->lockForUpdate()
                ->first();

            if ($existing && ! $existing->trashed()) {
                throw ValidationException::withMessages([
                    'engagementCode' => ['This engagement code is already used in the plan.'],
                ]);
            }

            if ($source && IapPlanEngagement::withTrashed()
                ->where('plan_id', $plan->id)
                ->where(function ($query) use ($source): void {
                    $query
                        ->where('prioritization_item_id', $source->id)
                        ->orWhere('audit_universe_item_id', $source->audit_universe_item_id);
                })
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'prioritizationItemId' => [
                        'This audit universe subject is already planned or archived in this annual plan.',
                    ],
                ]);
            }

            $attributes = $this->attributes($request, $plan, $source);
            if ($existing?->trashed()) {
                $existing->restore();
                $existing->fill($attributes)->save();
                $engagement = $existing;
            } else {
                $engagement = IapPlanEngagement::query()->create($attributes);
            }

            $this->syncCoverage($engagement, $request, $source);
            $this->incrementPlanLock($lockedPlan);
            $this->support->audit(
                $request,
                'iap.engagement.created',
                $engagement,
                null,
                $this->snapshot($engagement),
            );

            return $engagement;
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Proposed audit engagement created successfully.',
            'data' => ['engagement' => $this->payload($engagement)],
        ], 201);
    }

    public function update(
        IapEngagementRequest $request,
        InternalAuditPlan $plan,
        IapPlanEngagement $engagement,
    ): JsonResponse {
        $this->assertBelongsToPlan($plan, $engagement);
        $this->guard->assertEditable($request->user(), $plan);
        $source = $engagement->prioritization_item_id
            ? $engagement->prioritizationItem()->with(['run', 'auditUniverseItem'])->first()
            : null;
        $this->validateDomain($request, $plan, $source);

        DB::transaction(function () use ($request, $plan, $engagement, $source): void {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $locked = IapPlanEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            $this->guard->assertEditable($request->user(), $lockedPlan);
            if ($request->filled('lockVersion')) {
                $this->guard->assertLockVersion($lockedPlan, (int) $request->validated('lockVersion'));
            }

            $old = $this->snapshot($locked);
            $locked->fill($this->attributes($request, $plan, $source, $locked))->save();
            $this->syncCoverage($locked, $request, $source);
            $this->incrementPlanLock($lockedPlan);
            $this->support->audit(
                $request,
                'iap.engagement.updated',
                $locked,
                $old,
                $this->snapshot($locked),
            );
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Proposed audit engagement updated successfully.',
            'data' => ['engagement' => $this->payload($engagement->fresh())],
        ]);
    }

    public function updateTeam(
        IapTeamRequest $request,
        InternalAuditPlan $plan,
        IapPlanEngagement $engagement,
    ): JsonResponse {
        $this->assertBelongsToPlan($plan, $engagement);
        $this->guard->assertEditable($request->user(), $plan);
        $members = $request->validated('members');

        $roles = collect($members)->mapWithKeys(function (array $member): array {
            $role = $this->support->masterItem((int) $member['teamRoleId'], 'IAP_TEAM_ROLE');

            return [(int) $member['userId'] => $role->code];
        });

        if (! $roles->contains('LEAD_AUDITOR') || ! $roles->contains('REVIEWER')) {
            throw ValidationException::withMessages([
                'members' => ['Assign at least one Lead Auditor and one Reviewer.'],
            ]);
        }

        $plannedTotal = round((float) collect($members)->sum('plannedPersonDays'), 2);
        if (abs($plannedTotal - (float) $engagement->estimated_person_days) > 0.01) {
            throw ValidationException::withMessages([
                'members' => ['Assigned person-days must equal the engagement estimate.'],
            ]);
        }

        $eligibleCount = User::query()
            ->whereIn('id', collect($members)->pluck('userId'))
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.assign_team'))
            ->count();
        if ($eligibleCount !== count($members)) {
            throw ValidationException::withMessages([
                'members' => ['Every team member must be an active CIAS Management or AGIS User account.'],
            ]);
        }

        DB::transaction(function () use ($request, $plan, $engagement, $members): void {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $locked = IapPlanEngagement::query()->lockForUpdate()->findOrFail($engagement->id);
            if ($request->filled('lockVersion')) {
                $this->guard->assertLockVersion($lockedPlan, (int) $request->validated('lockVersion'));
            }

            $old = $this->snapshot($locked);
            $locked->teamMembers()->delete();
            foreach ($members as $member) {
                IapEngagementTeamMember::query()->create([
                    'plan_engagement_id' => $locked->id,
                    'user_id' => $member['userId'],
                    'team_role_id' => $member['teamRoleId'],
                    'planned_person_days' => $member['plannedPersonDays'],
                    'assignment_notes' => $member['notes'] ?? null,
                ]);
            }

            $this->incrementPlanLock($lockedPlan);
            $this->support->audit(
                $request,
                'iap.engagement.team_updated',
                $locked,
                $old,
                $this->snapshot($locked),
            );
        }, 3);

        $this->notifications->send(collect($members)->pluck('userId'), [
            'actorId' => $request->user()->id,
            'type' => 'IAP_TEAM_ASSIGNMENT',
            'category' => 'ASSIGNMENT',
            'priority' => 'HIGH',
            'moduleCode' => 'IAP',
            'title' => "Assigned to {$engagement->engagement_code}",
            'message' => "You were assigned to {$engagement->title} in {$plan->plan_code}.",
            'actionUrl' => "/internal-audit-planning/{$plan->id}",
            'actionLabel' => 'Open annual plan',
            'subjectType' => 'IAP_PLAN_ENGAGEMENT',
            'subjectId' => $engagement->id,
            'subjectCode' => $engagement->engagement_code,
            'dedupeKey' => "iap-team:{$engagement->id}",
            'renotify' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Engagement team assignment updated successfully.',
            'data' => ['engagement' => $this->payload($engagement->fresh())],
        ]);
    }

    public function destroy(
        Request $request,
        InternalAuditPlan $plan,
        IapPlanEngagement $engagement,
    ): JsonResponse {
        $this->assertBelongsToPlan($plan, $engagement);
        $this->guard->assertEditable($request->user(), $plan);

        if ($engagement->aem_engagement_id !== null) {
            throw ValidationException::withMessages([
                'engagement' => ['An engagement already linked to AEM cannot be archived from planning.'],
            ]);
        }

        DB::transaction(function () use ($request, $plan, $engagement): void {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $old = $this->snapshot($engagement);
            $engagement->forceFill(['is_active' => false])->save();
            $engagement->delete();
            $this->incrementPlanLock($lockedPlan);
            $this->support->audit($request, 'iap.engagement.archived', $engagement, $old, null);
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Proposed audit engagement archived successfully.',
        ]);
    }

    public function restore(Request $request, InternalAuditPlan $plan, int $engagement): JsonResponse
    {
        $this->guard->assertEditable($request->user(), $plan);
        $record = IapPlanEngagement::onlyTrashed()
            ->where('plan_id', $plan->id)
            ->findOrFail($engagement);

        DB::transaction(function () use ($request, $plan, $record): void {
            $lockedPlan = InternalAuditPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $record->restore();
            $record->forceFill(['is_active' => true])->save();
            $this->incrementPlanLock($lockedPlan);
            $this->support->audit($request, 'iap.engagement.restored', $record, null, $this->snapshot($record));
        }, 3);

        return response()->json([
            'success' => true,
            'message' => 'Proposed audit engagement restored successfully.',
            'data' => ['engagement' => $this->payload($record)],
        ]);
    }

    private function validateDomain(
        IapEngagementRequest $request,
        InternalAuditPlan $plan,
        ?IapPrioritizationItem $source = null,
    ): void {
        $this->support->masterItem((int) $request->validated('engagementTypeId'), 'IAP_ENGAGEMENT_TYPE');
        if ($request->validated('auditApproachId') !== null) {
            $this->support->masterItem((int) $request->validated('auditApproachId'), 'IAP_AUDIT_APPROACH');
        }
        $this->support->masterItem((int) $request->validated('priorityId'), 'IAP_PLANNING_PRIORITY');
        if (! $source) {
            $this->support->masterItem((int) $request->validated('riskLevelId'), 'RISK_LEVEL');
        }

        if ($request->date('plannedStartDate')->lt($plan->planning_period_start)
            || $request->date('plannedEndDate')->gt($plan->planning_period_end)) {
            throw ValidationException::withMessages([
                'plannedStartDate' => ['Engagement dates must fall within the plan period.'],
            ]);
        }

        $officeIds = $source
            ? collect([$source->auditUniverseItem?->responsible_office_id])->filter()
            : collect($request->validated('officeIds'))->map(fn ($id) => (int) $id);
        $areaIds = $source
            ? collect([$source->auditUniverseItem?->primary_audit_area_id])->filter()
            : collect($request->validated('auditAreaIds'))->map(fn ($id) => (int) $id);

        if ($source && ($officeIds->isEmpty() || $areaIds->isEmpty())) {
            throw ValidationException::withMessages([
                'prioritizationItemId' => [
                    'The source subject must retain an available responsible office and primary audit area.',
                ],
            ]);
        }
        $coverageCount = DB::table('audit_area_office')
            ->whereIn('office_id', $officeIds)
            ->whereIn('audit_area_id', $areaIds)
            ->count();
        if ($coverageCount !== $officeIds->count() * $areaIds->count()) {
            throw ValidationException::withMessages([
                'auditAreaIds' => ['Every selected audit area must be linked to every selected office.'],
            ]);
        }

        $focusIds = collect($request->validated('auditFocusIds', []))->map(fn ($id) => (int) $id);
        if ($focusIds->isNotEmpty()
            && AuditFocus::query()
                ->whereIn('id', $focusIds)
                ->whereNotIn('audit_area_id', $areaIds)
                ->exists()) {
            throw ValidationException::withMessages([
                'auditFocusIds' => ['Every selected audit focus must belong to a selected audit area.'],
            ]);
        }

        if ($request->validated('riskAssessmentId') !== null) {
            $risk = IapRiskAssessment::query()
                ->whereKey($request->validated('riskAssessmentId'))
                ->where('plan_id', $plan->id)
                ->first();
            if (! $risk
                || ! $officeIds->contains($risk->office_id)
                || ! $areaIds->contains($risk->audit_area_id)) {
                throw ValidationException::withMessages([
                    'riskAssessmentId' => ['Select a plan risk assessment covered by this engagement.'],
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    private function attributes(
        IapEngagementRequest $request,
        InternalAuditPlan $plan,
        ?IapPrioritizationItem $source = null,
        ?IapPlanEngagement $existing = null,
    ): array {
        $riskLevelId = $source
            ? $this->support->masterItemByCode('RISK_LEVEL', $source->risk_level_code)->id
            : $request->validated('riskLevelId');

        return [
            'plan_id' => $plan->id,
            'engagement_code' => $request->validated('engagementCode'),
            'title' => $request->validated('title'),
            'engagement_type_id' => $request->validated('engagementTypeId'),
            'audit_approach_id' => $request->validated('auditApproachId'),
            'priority_id' => $request->validated('priorityId'),
            'risk_level_id' => $riskLevelId,
            'risk_assessment_id' => $request->validated('riskAssessmentId'),
            'prioritization_item_id' => $source?->id ?? $existing?->prioritization_item_id,
            'audit_universe_item_id' => $source?->audit_universe_item_id ?? $existing?->audit_universe_item_id,
            'universe_risk_assessment_id' => $source?->risk_assessment_id ?? $existing?->universe_risk_assessment_id,
            'source_inherent_risk_score' => $source?->inherent_risk_score ?? $existing?->source_inherent_risk_score,
            'source_residual_risk_score' => $source?->residual_risk_score ?? $existing?->source_residual_risk_score,
            'source_priority_score' => $source?->priority_score ?? $existing?->source_priority_score,
            'source_risk_level_code' => $source?->risk_level_code ?? $existing?->source_risk_level_code,
            'source_decision' => $source?->decision ?? $existing?->source_decision,
            'source_final_rank' => $source?->final_rank ?? $existing?->source_final_rank,
            'target_quarter' => $request->validated('targetQuarter'),
            'imported_at' => $source && ! $existing ? now() : $existing?->imported_at,
            'imported_by' => $source && ! $existing ? $request->user()->id : $existing?->imported_by,
            'background' => $request->validated('background'),
            'objectives' => $request->validated('objectives'),
            'scope' => $request->validated('scope'),
            'exclusions' => $request->validated('exclusions'),
            'audit_criteria' => $request->validated('auditCriteria'),
            'proposed_methodology' => $request->validated('proposedMethodology'),
            'planned_start_date' => $request->validated('plannedStartDate'),
            'planned_end_date' => $request->validated('plannedEndDate'),
            'estimated_person_days' => $request->validated('estimatedPersonDays'),
            'estimated_cost' => $request->validated('estimatedCost'),
            'sequence_number' => $request->validated('sequenceNumber', 0),
            'planning_notes' => $request->validated('planningNotes'),
            'is_active' => true,
        ];
    }

    private function syncCoverage(
        IapPlanEngagement $engagement,
        IapEngagementRequest $request,
        ?IapPrioritizationItem $source = null,
    ): void {
        $engagement->offices()->sync(
            $source
                ? [$source->auditUniverseItem->responsible_office_id]
                : $request->validated('officeIds'),
        );
        $engagement->auditAreas()->sync(
            $source
                ? [$source->auditUniverseItem->primary_audit_area_id]
                : $request->validated('auditAreaIds'),
        );
        $engagement->auditFocuses()->sync($request->validated('auditFocusIds', []));
    }

    private function prioritizationSource(
        IapEngagementRequest $request,
        InternalAuditPlan $plan,
    ): ?IapPrioritizationItem {
        if (! $request->filled('prioritizationItemId')) {
            return null;
        }

        $source = IapPrioritizationItem::query()
            ->with(['run', 'auditUniverseItem', 'riskAssessment'])
            ->findOrFail((int) $request->validated('prioritizationItemId'));

        if ($plan->prioritization_run_id === null
            || $source->prioritization_run_id !== $plan->prioritization_run_id) {
            throw ValidationException::withMessages([
                'prioritizationItemId' => [
                    'This subject does not belong to the prioritization connected to the plan.',
                ],
            ]);
        }
        if ($source->run?->status !== 'FINALIZED'
            || ! $source->run->is_active
            || $source->run->trashed()
            || $source->decision !== 'SELECTED'
            || ! $source->riskAssessment
            || $source->riskAssessment->trashed()
            || ! in_array($source->riskAssessment->status, ['VALIDATED', 'LOCKED'], true)) {
            throw ValidationException::withMessages([
                'prioritizationItemId' => [
                    'Only selected subjects with validated assessments from an active, finalized prioritization may be imported.',
                ],
            ]);
        }

        return $source;
    }

    /** @return array<string, mixed> */
    private function snapshot(IapPlanEngagement $engagement): array
    {
        $engagement->load(['offices:id', 'auditAreas:id', 'auditFocuses:id', 'teamMembers']);

        return [
            ...$engagement->toArray(),
            'office_ids' => $engagement->offices->pluck('id')->all(),
            'audit_area_ids' => $engagement->auditAreas->pluck('id')->all(),
            'audit_focus_ids' => $engagement->auditFocuses->pluck('id')->all(),
            'team_members' => $engagement->teamMembers->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(IapPlanEngagement $engagement): array
    {
        $engagement->load([
            'offices:id,code,name',
            'auditAreas:id,code,name',
            'auditFocuses:id,code,name',
            'teamMembers.user:id,employee_id,name,initials',
            'teamMembers.teamRole',
        ]);

        return [
            'id' => $engagement->id,
            'engagementCode' => $engagement->engagement_code,
            'title' => $engagement->title,
            'estimatedPersonDays' => (float) $engagement->estimated_person_days,
            'offices' => $engagement->offices->map->only(['id', 'code', 'name'])->values(),
            'auditAreas' => $engagement->auditAreas->map->only(['id', 'code', 'name'])->values(),
            'auditFocuses' => $engagement->auditFocuses->map->only(['id', 'code', 'name'])->values(),
            'teamMembers' => $engagement->teamMembers->map(fn ($member) => [
                'id' => $member->id,
                'userId' => $member->user_id,
                'teamRoleId' => $member->team_role_id,
                'teamRoleCode' => $member->teamRole?->code,
                'plannedPersonDays' => (float) $member->planned_person_days,
            ])->values(),
        ];
    }

    private function assertBelongsToPlan(InternalAuditPlan $plan, IapPlanEngagement $engagement): void
    {
        if ($engagement->plan_id !== $plan->id) {
            abort(404);
        }
    }

    private function incrementPlanLock(InternalAuditPlan $plan): void
    {
        $plan->forceFill(['lock_version' => $plan->lock_version + 1])->save();
    }
}
