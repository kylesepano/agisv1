<?php

namespace App\Services;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Models\IapBaicsAssessment;
use App\Models\IapBaicsIntegration;
use App\Models\IapBaicsIntegrationVersion;
use App\Models\IapBaicsReport;
use App\Models\IapBaicsReportVersion;
use App\Models\IapPrioritizationRun;
use App\Models\IapPlanEngagement;
use App\Models\IapRiskPeriod;
use App\Models\IapUniverseRiskAssessment;
use App\Models\InternalAuditPlan;
use App\Models\StrategicInternalAuditPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Keeps BAICS consumption in an auditable integration ledger. The ledger
 * stores source snapshots; it never updates the risk, prioritization, or plan
 * records that it references.
 */
class IapBaicsIntegrationService
{
    public function __construct(
        private readonly IapSupport $support,
        private readonly RuntimeConfiguration $runtime,
        private readonly ResourcePlanningGateway $resources,
        private readonly IapBaicsIntegrationNotificationService $notifications,
    ) {}

    public function listForAssessment(User $user, IapBaicsAssessment $assessment): \Illuminate\Support\Collection
    {
        $this->assertAssessmentVisible($user, $assessment);
        return IapBaicsIntegration::query()
            ->where('assessment_id', $assessment->id)
            ->with(['reportVersion', 'reviewer:id,employee_id,name,initials,position', 'authority:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position', 'creator:id,employee_id,name,initials,position', 'versions.creator:id,employee_id,name,initials'])
            ->orderByDesc('id')
            ->get();
    }

    /** @return array<string, mixed> */
    public function candidates(User $user): array
    {
        $periods = IapRiskPeriod::query()->withCount('assessments')->orderByDesc('assessment_year')->limit(100);
        $riskAssessments = IapUniverseRiskAssessment::query()->with(['period:id,period_code,name,assessment_year,status', 'auditUniverseItem:id,subject_code,name,responsible_office_id'])->orderByDesc('id')->limit(200);
        $prioritizations = IapPrioritizationRun::query()->with(['riskPeriod:id,period_code,name,assessment_year,status'])->withCount('items')->orderByDesc('id')->limit(100);
        $strategic = StrategicInternalAuditPlan::query()->orderByDesc('start_year')->limit(100);
        $annual = InternalAuditPlan::query()->withCount('engagements')->orderByDesc('fiscal_year')->limit(100);
        $engagements = IapPlanEngagement::query()->with(['plan:id,plan_code,fiscal_year', 'auditUniverseItem:id,subject_code,name,responsible_office_id'])->orderByDesc('id')->limit(200);
        if (! $user->hasGlobalOfficeAccess()) {
            $officeId = (int) $user->office_id;
            $periods->whereHas('assessments.auditUniverseItem', fn ($query) => $query->where('responsible_office_id', $officeId));
            $riskAssessments->whereHas('auditUniverseItem', fn ($query) => $query->where('responsible_office_id', $officeId));
            $prioritizations->whereHas('items.auditUniverseItem', fn ($query) => $query->where('responsible_office_id', $officeId));
            $annual->whereHas('engagements.offices', fn ($query) => $query->where('offices.id', $officeId));
            $strategic->whereHas('objectives.auditAreas.offices', fn ($query) => $query->where('offices.id', $officeId));
            $engagements->whereHas('auditUniverseItem', fn ($query) => $query->where('responsible_office_id', $officeId));
        }
        $provider = $this->providerStatus();
        return [
            'enforcementEnabled' => $this->runtime->boolean('baics_integration_required'),
            'provider' => $provider,
            'riskPeriods' => $periods->get()->map(fn (IapRiskPeriod $period): array => ['id' => $period->id, 'code' => $period->period_code, 'name' => $period->name, 'assessmentYear' => $period->assessment_year, 'status' => $period->status, 'assessmentCount' => $period->assessments_count])->values()->all(),
            'riskAssessments' => $riskAssessments->get()->map(fn (IapUniverseRiskAssessment $record): array => ['id' => $record->id, 'code' => $record->auditUniverseItem?->subject_code, 'name' => $record->auditUniverseItem?->name, 'periodId' => $record->period_id, 'periodCode' => $record->period?->period_code, 'assessmentYear' => $record->period?->assessment_year, 'status' => $record->status])->values()->all(),
            'prioritizations' => $prioritizations->get()->map(fn (IapPrioritizationRun $run): array => ['id' => $run->id, 'code' => $run->run_code, 'name' => $run->name, 'riskPeriodId' => $run->risk_period_id, 'assessmentYear' => $run->riskPeriod?->assessment_year, 'status' => $run->status, 'itemCount' => $run->items_count])->values()->all(),
            'strategicPlans' => $strategic->get()->map(fn (StrategicInternalAuditPlan $plan): array => ['id' => $plan->id, 'code' => $plan->plan_code, 'title' => $plan->title, 'startYear' => $plan->start_year, 'endYear' => $plan->end_year, 'status' => $plan->status])->values()->all(),
            'annualPlans' => $annual->get()->map(fn (InternalAuditPlan $plan): array => ['id' => $plan->id, 'code' => $plan->plan_code, 'title' => $plan->title, 'fiscalYear' => $plan->fiscal_year, 'status' => $plan->status, 'engagementCount' => $plan->engagements_count])->values()->all(),
            'annualPlanEngagements' => $engagements->get()->map(fn (IapPlanEngagement $engagement): array => ['id' => $engagement->id, 'code' => $engagement->engagement_code, 'title' => $engagement->title, 'planId' => $engagement->plan_id, 'planCode' => $engagement->plan?->plan_code, 'status' => $engagement->schedule_status ?? ($engagement->plan?->status ?? null), 'subjectCode' => $engagement->auditUniverseItem?->subject_code])->values()->all(),
        ];
    }

    public function save(Request $request, IapBaicsAssessment $assessment, array $data, ?IapBaicsIntegration $integration = null): IapBaicsIntegration
    {
        $this->assertAssessmentVisible($request->user(), $assessment);
        if ($integration && ((int) $integration->assessment_id !== (int) $assessment->id || ! in_array($integration->status, ['DRAFT', 'RETURNED'], true))) {
            throw ValidationException::withMessages(['status' => ['Only draft or returned integration decisions can be changed.']]);
        }
        $consumer = $this->consumer($data['consumerType'], (int) $data['consumerId']);
        $this->assertConsumerVisible($request->user(), $data['consumerType'], $consumer);
        $decisionType = strtoupper($data['decisionType']);
        if (! in_array($decisionType, IapBaicsIntegration::DECISION_TYPES, true)) abort(422);
        if ($decisionType === 'BAICS_BACKED') $source = $this->approvedSource($assessment, $data);
        else $source = $this->legacySource($request->user(), $data);
        $this->assertNoActiveDuplicate($data['consumerType'], (int) $data['consumerId'], $integration?->id);
        $attributes = [
            'assessment_id' => $assessment->id,
            'report_id' => $source['report']?->id,
            'report_version_id' => $source['version']?->id,
            'consumer_type' => $data['consumerType'],
            'consumer_id' => (int) $data['consumerId'],
            'decision_type' => $decisionType,
            'decision_reason' => $data['decisionReason'] ?? null,
            'legacy_reason' => $data['legacyReason'] ?? null,
            'compensating_source' => $data['compensatingSource'] ?? null,
            'reviewer_id' => (int) $data['reviewerId'],
            'authority_user_id' => isset($data['authorityUserId']) ? (int) $data['authorityUserId'] : null,
            'expires_at' => $data['expiresAt'] ?? null,
            'consumer_snapshot' => $this->consumerSnapshot($consumer),
            'source_snapshot' => $source['snapshot'],
            'provider_snapshot' => $this->providerStatus(),
            'source_manifest_sha256' => $source['version']?->source_manifest_sha256,
        ];
        if (! $attributes['authority_user_id']) throw ValidationException::withMessages(['authorityUserId' => ['An independent approving authority is required.']]);
        $this->assertAssignedUsers($request, (int) $attributes['reviewer_id'], (int) $attributes['authority_user_id']);
        if ((int) $attributes['reviewer_id'] === (int) $attributes['authority_user_id'] || (int) $attributes['reviewer_id'] === (int) $request->user()->id || (int) $attributes['authority_user_id'] === (int) $request->user()->id) {
            throw ValidationException::withMessages(['reviewerId' => ['Preparer, reviewer, and approving authority must be separate users.']]);
        }
        $created = ! $integration;
        $saved = DB::transaction(function () use ($request, $assessment, $integration, $attributes, $data): IapBaicsIntegration {
            if ($integration) {
                $this->assertLock($integration->lock_version, (int) ($data['lockVersion'] ?? 0));
                $integration->forceFill([...$attributes, 'version_number' => $integration->version_number + 1, 'lock_version' => $integration->lock_version + 1])->save();
            } else {
                $integration = IapBaicsIntegration::query()->create([...$attributes, 'integration_code' => $this->nextCode(), 'status' => 'DRAFT', 'created_by' => $request->user()->id, 'version_number' => 1, 'lock_version' => 1]);
            }
            $this->snapshot($integration, $request->user(), $integration->exists ? 'Integration decision saved' : 'Integration decision created');
            $this->support->audit($request, 'iap.baics.integration.saved', $integration, null, $this->values($integration));
            return $integration;
        }, 3);
        $this->notifications->saved($request, $saved, $created);
        return $this->load($saved);
    }

    public function transition(Request $request, IapBaicsIntegration $integration, string $action, ?string $comment = null): IapBaicsIntegration
    {
        $action = strtoupper($action);
        $map = ['SUBMIT' => [['DRAFT', 'RETURNED'], 'PENDING_REVIEW'], 'RETURN' => [['PENDING_REVIEW'], 'RETURNED'], 'REVIEW' => [['PENDING_REVIEW'], 'PENDING_REVIEW'], 'APPROVE' => [['PENDING_REVIEW'], 'APPROVED'], 'RETIRE' => [['APPROVED'], 'RETIRED']];
        abort_unless(isset($map[$action]), 404);
        $integration->loadMissing('assessment');
        if ($integration->assessment) $this->assertAssessmentVisible($request->user(), $integration->assessment);
        if (! in_array($integration->status, $map[$action][0], true)) throw ValidationException::withMessages(['status' => ["{$action} is not available while this integration is {$integration->status}."]]);
        if (in_array($action, ['RETURN', 'RETIRE'], true) && blank(trim((string) $comment))) throw ValidationException::withMessages(['comment' => ['A reason is required.']]);
        if ($action === 'REVIEW' && (int) $request->user()->id !== (int) $integration->reviewer_id) throw ValidationException::withMessages(['reviewer' => ['Only the assigned independent reviewer can review this decision.']]);
        if ($action === 'RETURN' && ! in_array((int) $request->user()->id, [(int) $integration->reviewer_id, (int) $integration->authority_user_id], true)) throw ValidationException::withMessages(['reviewer' => ['Only the assigned reviewer or approving authority can return this decision.']]);
        if ($action === 'RETIRE' && (int) $request->user()->id !== (int) $integration->authority_user_id) throw ValidationException::withMessages(['authority' => ['Only the approving authority can retire an approved integration decision.']]);
        if ($action === 'APPROVE') {
            if (! $integration->reviewed_at) throw ValidationException::withMessages(['reviewer' => ['An independent review must be completed before approval.']]);
            if ((int) $request->user()->id !== (int) $integration->authority_user_id || (int) $request->user()->id === (int) $integration->created_by || (int) $request->user()->id === (int) $integration->reviewer_id) throw ValidationException::withMessages(['authority' => ['Only the independent approving authority may approve this decision.']]);
            if ($integration->decision_type === 'BAICS_BACKED') $this->approvedSource($integration->assessment, ['reportId' => $integration->report_id, 'reportVersionId' => $integration->report_version_id]);
            else $this->legacySource($request->user(), ['legacyReason' => $integration->legacy_reason, 'compensatingSource' => $integration->compensating_source, 'authorityUserId' => $integration->authority_user_id, 'expiresAt' => $integration->expires_at?->toDateString()], false);
        }
        $old = $integration->status;
        $integration->forceFill(['status' => $map[$action][1], 'version_number' => $integration->version_number + 1, 'lock_version' => $integration->lock_version + 1, ...($action === 'SUBMIT' ? ['submitted_at' => now()] : []), ...($action === 'REVIEW' ? ['reviewed_at' => now(), 'decision_reason' => trim(($integration->decision_reason ? $integration->decision_reason."\n" : '').'Reviewed: '.($comment ?: 'Independent review completed.'))] : []), ...($action === 'APPROVE' ? ['approved_by' => $request->user()->id, 'approved_at' => now()] : []), ...($action === 'RETIRE' ? ['retired_at' => now()] : [])])->save();
        $this->snapshot($integration, $request->user(), $comment ?: $action);
        $this->support->audit($request, 'iap.baics.integration.'.strtolower($action), $integration, ['status' => $old], ['status' => $integration->status], ['comment' => $comment]);
        $this->notifications->transitioned($request, $integration, $action, $old);
        return $this->load($integration->fresh());
    }

    /** @return array<string, mixed> */
    public function readinessFor(User $user, string $consumerType, int $consumerId): array
    {
        $consumer = $this->consumer($consumerType, $consumerId);
        $this->assertConsumerVisible($user, $consumerType, $consumer);
        return $this->readinessForConsumer($consumerType, $consumerId, $consumer);
    }

    public function readiness(string $consumerType, int $consumerId): array
    {
        $consumer = $this->consumer($consumerType, $consumerId);
        return $this->readinessForConsumer($consumerType, $consumerId, $consumer);
    }

    /** @param Model $consumer */
    private function readinessForConsumer(string $consumerType, int $consumerId, Model $consumer): array
    {
        $active = IapBaicsIntegration::query()->where('consumer_type', $consumerType)->where('consumer_id', $consumerId)->where('status', 'APPROVED')->latest('id')->first();
        $valid = $active && ($active->expires_at === null || $active->expires_at->isFuture());
        return ['consumerType' => $consumerType, 'consumerId' => $consumerId, 'ready' => (bool) $valid, 'decision' => $active ? ['id' => $active->id, 'type' => $active->decision_type, 'status' => $active->status, 'expiresAt' => $active->expires_at?->toDateString()] : null, 'reasons' => $valid ? [] : ['An approved BAICS baseline or explicit legacy exception is required.'], 'consumer' => $this->consumerSnapshot($consumer)];
    }

    public function assertReady(string $consumerType, int $consumerId): void
    {
        $result = $this->readiness($consumerType, $consumerId);
        if (! $result['ready']) throw ValidationException::withMessages(['baicsIntegration' => $result['reasons']]);
    }

    public function assertRiskPeriodReady(IapRiskPeriod $period): void
    {
        if (IapBaicsIntegration::query()->where('consumer_type', 'RISK_PERIOD')->where('consumer_id', $period->id)->where('status', 'APPROVED')->exists()) return;
        $assessmentIds = $period->assessments()->pluck('id');
        if ($assessmentIds->isEmpty()) throw ValidationException::withMessages(['baicsIntegration' => ['The risk period must have an approved BAICS period decision or at least one approved BAICS risk-assessment decision.']]);
        foreach ($assessmentIds as $id) $this->assertReady('UNIVERSE_RISK_ASSESSMENT', (int) $id);
    }

    public function assertPrioritizationReady(IapPrioritizationRun $run): void
    {
        $assessmentIds = $run->items()->pluck('risk_assessment_id');
        if ($assessmentIds->isEmpty()) throw ValidationException::withMessages(['baicsIntegration' => ['The prioritization run must contain at least one risk assessment before it can be approved.']]);
        foreach ($assessmentIds as $id) $this->assertReady('UNIVERSE_RISK_ASSESSMENT', (int) $id);
    }

    public function assertStrategicPlanReady(StrategicInternalAuditPlan $plan): void { $this->assertReady('STRATEGIC_PLAN', (int) $plan->id); }
    public function assertAnnualPlanReady(InternalAuditPlan $plan): void { $this->assertReady('ANNUAL_PLAN', (int) $plan->id); }

    private function approvedSource(IapBaicsAssessment $assessment, array $data): array
    {
        if (! in_array($assessment->status, ['APPROVED', 'PUBLISHED'], true)) throw ValidationException::withMessages(['assessment' => ['The BAICS cycle must be approved or published before it can feed IAP decisions.']]);
        $report = IapBaicsReport::query()->where('assessment_id', $assessment->id)->whereKey((int) ($data['reportId'] ?? 0))->first();
        $version = IapBaicsReportVersion::query()->where('report_id', $report?->id)->whereKey((int) ($data['reportVersionId'] ?? 0))->first();
        if (! $report || ! in_array($report->status, ['APPROVED', 'ISSUED'], true) || ! $version || ! in_array($version->status, ['APPROVED', 'ISSUED'], true)) throw ValidationException::withMessages(['reportVersionId' => ['Select an approved immutable BAR version belonging to this BAICS cycle.']]);
        return ['report' => $report, 'version' => $version, 'snapshot' => ['assessmentId' => $assessment->id, 'assessmentCode' => $assessment->assessment_code, 'assessmentVersion' => $assessment->version_number, 'reportId' => $report->id, 'reportCode' => $report->report_code, 'reportVersionId' => $version->id, 'reportVersion' => $version->version_number, 'reportContentSha256' => $version->content_sha256, 'scopeItemIds' => $assessment->scopeItems()->pluck('audit_universe_item_id')->values()->all()]];
    }

    private function legacySource(User $actor, array $data, bool $enforceCreatorSeparation = true): array
    {
        if (blank($data['legacyReason'] ?? null) || blank($data['compensatingSource'] ?? null) || blank($data['authorityUserId'] ?? null) || blank($data['expiresAt'] ?? null)) throw ValidationException::withMessages(['legacyReason' => ['A legacy exception requires a reason, compensating source, approving authority, and expiry date.']]);
        if (Carbon::parse($data['expiresAt'])->isPast()) throw ValidationException::withMessages(['expiresAt' => ['Legacy exceptions must expire in the future.']]);
        if ($enforceCreatorSeparation && (int) $data['authorityUserId'] === (int) $actor->id) throw ValidationException::withMessages(['authorityUserId' => ['The exception creator cannot be its approving authority.']]);
        return ['report' => null, 'version' => null, 'snapshot' => ['decisionType' => 'LEGACY_EXCEPTION', 'legacyReason' => $data['legacyReason'], 'compensatingSource' => $data['compensatingSource'], 'authorityUserId' => (int) $data['authorityUserId'], 'expiresAt' => $data['expiresAt']]];
    }

    private function consumer(string $type, int $id): Model
    {
        $model = match ($type) {
            'RISK_PERIOD' => IapRiskPeriod::query()->with('assessments.auditUniverseItem')->find($id),
            'UNIVERSE_RISK_ASSESSMENT' => IapUniverseRiskAssessment::query()->with(['period', 'auditUniverseItem.responsibleOffice'])->find($id),
            'PRIORITIZATION_RUN' => IapPrioritizationRun::query()->with(['riskPeriod', 'items.auditUniverseItem'])->find($id),
            'STRATEGIC_PLAN' => StrategicInternalAuditPlan::query()->with('objectives.auditAreas.offices')->find($id),
            'ANNUAL_PLAN' => InternalAuditPlan::query()->with('engagements.offices')->find($id),
            'ANNUAL_PLAN_ENGAGEMENT' => IapPlanEngagement::query()->with(['plan', 'auditUniverseItem.responsibleOffice'])->find($id),
            default => null,
        };
        if (! $model) throw ValidationException::withMessages(['consumerId' => ['The selected IAP consumer does not exist or is archived.']]);
        return $model;
    }

    private function consumerSnapshot(Model $consumer): array
    {
        return match (true) {
            $consumer instanceof IapRiskPeriod => ['id' => $consumer->id, 'code' => $consumer->period_code, 'name' => $consumer->name, 'assessmentYear' => $consumer->assessment_year, 'status' => $consumer->status],
            $consumer instanceof IapUniverseRiskAssessment => ['id' => $consumer->id, 'subjectCode' => $consumer->auditUniverseItem?->subject_code, 'subjectName' => $consumer->auditUniverseItem?->name, 'periodId' => $consumer->period_id, 'assessmentYear' => $consumer->period?->assessment_year, 'status' => $consumer->status],
            $consumer instanceof IapPrioritizationRun => ['id' => $consumer->id, 'code' => $consumer->run_code, 'name' => $consumer->name, 'riskPeriodId' => $consumer->risk_period_id, 'assessmentYear' => $consumer->riskPeriod?->assessment_year, 'status' => $consumer->status],
            $consumer instanceof StrategicInternalAuditPlan => ['id' => $consumer->id, 'code' => $consumer->plan_code, 'title' => $consumer->title, 'startYear' => $consumer->start_year, 'endYear' => $consumer->end_year, 'status' => $consumer->status],
            $consumer instanceof InternalAuditPlan => ['id' => $consumer->id, 'code' => $consumer->plan_code, 'title' => $consumer->title, 'fiscalYear' => $consumer->fiscal_year, 'status' => $consumer->status],
            $consumer instanceof IapPlanEngagement => ['id' => $consumer->id, 'code' => $consumer->engagement_code, 'title' => $consumer->title, 'planId' => $consumer->plan_id, 'fiscalYear' => $consumer->plan?->fiscal_year, 'auditUniverseItemId' => $consumer->audit_universe_item_id],
            default => ['id' => $consumer->getKey()],
        };
    }

    private function assertConsumerVisible(User $user, string $type, Model $consumer): void
    {
        if ($user->hasGlobalOfficeAccess()) return;
        $officeId = (int) $user->office_id;
        $visible = match (true) {
            $consumer instanceof IapRiskPeriod => $consumer->assessments->contains(fn ($assessment) => (int) $assessment->auditUniverseItem?->responsible_office_id === $officeId),
            $consumer instanceof IapUniverseRiskAssessment => (int) $consumer->auditUniverseItem?->responsible_office_id === $officeId,
            $consumer instanceof IapPrioritizationRun => $consumer->items->contains(fn ($item) => (int) $item->auditUniverseItem?->responsible_office_id === $officeId),
            $consumer instanceof StrategicInternalAuditPlan => $consumer->objectives->contains(fn ($objective) => $objective->auditAreas->contains(fn ($area) => (int) $area->responsible_office_id === $officeId || $area->offices->contains('id', $officeId))),
            $consumer instanceof InternalAuditPlan => $consumer->engagements->contains(fn ($engagement) => $engagement->offices->contains('id', $officeId)),
            $consumer instanceof IapPlanEngagement => (int) $consumer->auditUniverseItem?->responsible_office_id === $officeId,
            default => false,
        };
        abort_unless($visible, 403, 'This IAP consumer is outside your office scope.');
    }

    private function assertAssessmentVisible(User $user, IapBaicsAssessment $assessment): void { abort_unless($user->hasGlobalOfficeAccess() || (int) $assessment->responsible_office_id === (int) $user->office_id || $assessment->scopeItems()->where('office_id', $user->office_id)->exists(), 403, 'This BAICS cycle is outside your office scope.'); }
    private function assertAssignedUsers(Request $request, int $reviewerId, int $authorityId): void
    {
        $users = User::query()->whereIn('id', [$reviewerId, $authorityId])->where('is_active', true)->with(['role.permissions', 'roles.permissions'])->get()->keyBy('id');
        if (! $users->has($reviewerId) || ! $users->get($reviewerId)->hasAnyPermission(['iap.baics.integration.review', 'iap.baics.review'])) {
            throw ValidationException::withMessages(['reviewerId' => ['The independent reviewer must be an active user with BAICS review authority.']]);
        }
        if (! $users->has($authorityId) || ! $users->get($authorityId)->hasAnyPermission(['iap.baics.integration.approve', 'iap.baics.approve'])) {
            throw ValidationException::withMessages(['authorityUserId' => ['The approving authority must be an active user with BAICS approval authority.']]);
        }
        if ((int) $request->user()->id === $reviewerId || (int) $request->user()->id === $authorityId) {
            throw ValidationException::withMessages(['reviewerId' => ['The preparer cannot also be the reviewer or approving authority.']]);
        }
    }
    private function assertNoActiveDuplicate(string $type, int $id, ?int $ignoreId = null): void { $query = IapBaicsIntegration::query()->where('consumer_type', $type)->where('consumer_id', $id)->whereIn('status', ['DRAFT', 'PENDING_REVIEW', 'RETURNED', 'APPROVED']); if ($ignoreId) $query->whereKeyNot($ignoreId); if ($query->exists()) throw ValidationException::withMessages(['consumerId' => ['This IAP consumer already has an active or pending BAICS integration decision.']]); }
    private function assertLock(int $current, int $provided): void { if ($current !== $provided) throw ValidationException::withMessages(['lockVersion' => ['This integration decision changed. Refresh before continuing.']]); }
    private function load(IapBaicsIntegration $integration): IapBaicsIntegration { return $integration->load(['reportVersion', 'reviewer:id,employee_id,name,initials,position', 'authority:id,employee_id,name,initials,position', 'approver:id,employee_id,name,initials,position', 'creator:id,employee_id,name,initials,position', 'versions.creator:id,employee_id,name,initials']); }
    private function values(IapBaicsIntegration $integration): array { return $integration->only(['id', 'integration_code', 'assessment_id', 'report_id', 'report_version_id', 'consumer_type', 'consumer_id', 'decision_type', 'status', 'decision_reason', 'legacy_reason', 'compensating_source', 'reviewer_id', 'authority_user_id', 'approved_by', 'submitted_at', 'reviewed_at', 'approved_at', 'retired_at', 'expires_at', 'consumer_snapshot', 'source_snapshot', 'provider_snapshot', 'source_manifest_sha256', 'version_number', 'lock_version']); }
    private function snapshot(IapBaicsIntegration $integration, User $actor, string $reason): void { $values = $this->values($integration); $canonical = json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); IapBaicsIntegrationVersion::query()->create(['integration_id' => $integration->id, 'version_number' => $integration->version_number, 'status' => $integration->status, 'snapshot' => $values, 'snapshot_sha256' => hash('sha256', $canonical), 'reason' => $reason, 'created_by' => $actor->id]); }
    private function nextCode(): string { return 'BAICS-IAP-'.str_pad((string) ((int) IapBaicsIntegration::query()->max('id') + 1), 5, '0', STR_PAD_LEFT); }
    private function providerStatus(): array { try { return $this->resources->status(); } catch (\Throwable $exception) { return ['available' => false, 'provider' => 'UNAVAILABLE', 'reason' => $exception->getMessage()]; } }
}
