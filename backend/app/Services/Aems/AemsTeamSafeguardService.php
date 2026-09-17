<?php

namespace App\Services;

use App\Contracts\Aems\ResourcePlanningGateway;
use App\Integrations\Aems\ConfigurableResourcePlanningGateway;
use App\Models\AemsTeamSafeguardAssessment;
use App\Models\AemsTeamSafeguardDeclaration;
use App\Models\AuditEngagement;
use App\Models\EngagementTeam;
use App\Models\ArmisResourceProfile;
use App\Models\DocumentVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Governs AEMS team declarations and the provider-aware readiness decision.
 *
 * ARMIS is the sole operational resource authority. Historical provider
 * reconciliation records may remain available for migration lineage, but they
 * are not an assignment-readiness prerequisite and no IAP fallback is used.
 */
class AemsTeamSafeguardService
{
    private const REQUIRED_ROLES = ['SUPERVISOR', 'TEAM_LEADER', 'AUDITOR', 'REVIEWER'];

    private const PROFICIENCY_RANKS = [
        'BASIC' => 1,
        'INTERMEDIATE' => 2,
        'ADVANCED' => 3,
        'EXPERT' => 4,
    ];

    public function __construct(
        private readonly ResourcePlanningGateway $resources,
        private readonly AemsSupport $support,
        private readonly AemsNotificationService $notifications,
    ) {}

    /** @return array<string, mixed> */
    public function overview(AuditEngagement $engagement): array
    {
        $evaluation = $this->evaluate($engagement);
        $declarations = AemsTeamSafeguardDeclaration::query()
            ->where('audit_engagement_id', $engagement->id)
            ->with(['teamMember.user', 'user', 'reviewer', 'evidenceDocumentVersion'])
            ->orderByDesc('created_at')
            ->get();
        $assessments = AemsTeamSafeguardAssessment::query()
            ->where('audit_engagement_id', $engagement->id)
            ->with(['assessor', 'approver'])
            ->orderByDesc('version_number')
            ->get();

        return [
            'engagement' => [
                'id' => $engagement->id,
                'engagementCode' => $engagement->engagement_code,
                'title' => $engagement->title,
                'status' => $engagement->status,
            ],
            'provider' => $evaluation['provider'],
            'requirements' => $evaluation['requirements'],
            'team' => $evaluation['team'],
            'reconciliation' => $evaluation['reconciliation'],
            'checks' => $evaluation['checks'],
            'blockers' => $evaluation['blockers'],
            'warnings' => $evaluation['warnings'],
            'approvalReady' => $evaluation['approvalReady'],
            'declarations' => $declarations->map(fn (AemsTeamSafeguardDeclaration $declaration): array => $this->declaration($declaration))->values(),
            'assessments' => $assessments->map(fn (AemsTeamSafeguardAssessment $assessment): array => $this->assessment($assessment))->values(),
            'latestApprovedAssessment' => $assessments->firstWhere('status', 'APPROVED')
                ? $this->assessment($assessments->firstWhere('status', 'APPROVED'))
                : null,
        ];
    }

    /** @return array<string, mixed> */
    public function evaluate(AuditEngagement $engagement): array
    {
        $engagement->loadMissing([
            'teamMembers' => fn ($query) => $query
                ->where('is_active', true)
                ->whereNull('ended_at')
                ->with('user'),
            'offices',
        ]);
        $team = $engagement->teamMembers->values();
        $provider = $this->resources->status();
        $mode = ConfigurableResourcePlanningGateway::ARMIS_AUTHORITATIVE;
        $authoritative = true;
        $blockers = [];
        $warnings = [];
        $checks = [];

        $this->check(
            $checks,
            $blockers,
            'providerAvailable',
            'Resource provider is available',
            (bool) ($provider['available'] ?? false),
            'RESOURCE_PROVIDER_UNAVAILABLE',
        );
        if ($authoritative) {
            $this->check(
                $checks,
                $blockers,
                'providerAuthority',
                'ARMIS authority decision is active and eligible',
                (bool) ($provider['authoritative'] ?? false)
                    && (bool) ($provider['authorityEligible'] ?? false),
                'RESOURCE_AUTHORITY_NOT_ELIGIBLE',
            );
        }

        $roleCodes = $team->pluck('assignment_role_code');
        $missingRoles = collect(self::REQUIRED_ROLES)->diff($roleCodes)->values()->all();
        $this->check(
            $checks,
            $blockers,
            'requiredRoles',
            'Supervisor, Team Leader, Auditor, and Reviewer are active',
            $missingRoles === [],
            'REQUIRED_ROLE_MISSING',
            ['missingRoles' => $missingRoles],
        );

        $plannedTeam = round((float) $team->sum('planned_person_days'), 2);
        $requiredPlanned = round((float) $engagement->planned_person_days, 2);
        $plannedVariance = round($plannedTeam - $requiredPlanned, 2);
        $this->check(
            $checks,
            $blockers,
            'plannedPersonDays',
            'Assigned planned person-days reconcile to the engagement plan',
            abs($plannedVariance) <= 0.009,
            'PLANNED_PERSON_DAY_MISMATCH',
            ['assigned' => $plannedTeam, 'required' => $requiredPlanned, 'variance' => $plannedVariance],
        );

        $requirements = collect($this->resources->requirements($engagement));
        $skillMap = $this->resources->skills(
            $team->pluck('user_id')->map(fn ($id): int => (int) $id)->all(),
            $requirements->pluck('specializationId')->filter()->map(fn ($id): int => (int) $id)->all(),
        );
        $skillChecks = $this->competencyChecks($requirements, $team, $skillMap);
        foreach ($skillChecks as $skillCheck) {
            $this->check(
                $checks,
                $blockers,
                'competency_'.$skillCheck['key'],
                $skillCheck['label'],
                $skillCheck['met'],
                'MANDATORY_COMPETENCY_GAP',
                $skillCheck,
            );
        }

        $teamSnapshots = [];
        $actualTeam = 0.0;
        foreach ($team as $member) {
            $snapshot = $this->memberEvaluation($engagement, $member, $authoritative);
            $teamSnapshots[] = $snapshot;
            $actualTeam += (float) $snapshot['actualPersonDays'];
            foreach ($snapshot['blockers'] as $blocker) {
                $blockers[] = $blocker;
            }
            foreach ($snapshot['warnings'] as $warning) {
                $warnings[] = $warning;
            }
        }

        $providerActual = round((float) $this->resources->engagementActualPersonDays($engagement), 2);
        $engagementActual = round((float) $engagement->actual_person_days, 2);
        $actualVariance = round($providerActual - $engagementActual, 2);
        $teamActualVariance = round($actualTeam - $providerActual, 2);
        $actualReconciles = abs($actualVariance) <= 0.009 && abs($teamActualVariance) <= 0.009;
        if ($engagementActual > 0 || $providerActual > 0 || $actualTeam > 0) {
            $this->check(
                $checks,
                $blockers,
                'actualPersonDays',
                'Actual person-days reconcile across AEMS and the resource provider',
                $actualReconciles,
                'ACTUAL_PERSON_DAY_MISMATCH',
                [
                    'engagement' => $engagementActual,
                    'provider' => $providerActual,
                    'team' => round($actualTeam, 2),
                    'providerVariance' => $actualVariance,
                    'teamVariance' => $teamActualVariance,
                ],
            );
        } else {
            $checks['actualPersonDays'] = [
                'label' => 'Actual person-days reconciliation',
                'met' => true,
                'state' => 'NOT_STARTED',
            ];
        }

        $declarationCheck = $this->declarationChecks($team);
        foreach ($declarationCheck['missing'] as $missing) {
            $this->block($blockers, 'DECLARATION_MISSING', $missing['message'], $missing);
        }
        foreach ($declarationCheck['conflicts'] as $conflict) {
            $this->block($blockers, 'INDEPENDENCE_CONFLICT', $conflict['message'], $conflict);
        }
        $checks['declarations'] = [
            'label' => 'Current objectivity, conflict-of-interest, and independence declarations are accepted',
            'met' => $declarationCheck['missing'] === [] && $declarationCheck['conflicts'] === [],
            'missing' => $declarationCheck['missing'],
            'conflicts' => $declarationCheck['conflicts'],
        ];

        return [
            'provider' => [
                ...$provider,
                'mode' => $mode,
                'authoritative' => $authoritative,
                'resourceSource' => 'ARMIS',
                'reconciliationRequiredForAuthority' => false,
            ],
            'requirements' => $requirements->values()->all(),
            'team' => $teamSnapshots,
            'reconciliation' => [
                'planned' => [
                    'team' => $plannedTeam,
                    'engagement' => $requiredPlanned,
                    'variance' => $plannedVariance,
                    'reconciled' => abs($plannedVariance) <= 0.009,
                ],
                'actual' => [
                    'team' => round($actualTeam, 2),
                    'provider' => $providerActual,
                    'engagement' => $engagementActual,
                    'providerVariance' => $actualVariance,
                    'teamVariance' => $teamActualVariance,
                    'reconciled' => $actualReconciles,
                ],
            ],
            'checks' => $checks,
            'blockers' => array_values($blockers),
            'warnings' => array_values($warnings),
            'approvalReady' => $blockers === [],
        ];
    }

    public function submitDeclaration(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $member,
        array $attributes,
    ): AemsTeamSafeguardDeclaration {
        $this->ensureMember($engagement, $member);
        $actor = $request->user();
        if ((int) $actor->id !== (int) $member->user_id
            && ! $actor->hasPermission('aems.team.safeguard_submit_on_behalf')) {
            throw ValidationException::withMessages(['declaration' => ['Only the assigned resource may submit this declaration.']]);
        }
        if (! empty($attributes['evidenceDocumentVersionId'])) {
            $documentVersion = DocumentVersion::query()->with('document')->find((int) $attributes['evidenceDocumentVersionId']);
            abort_unless(
                $documentVersion && $documentVersion->document && ! $documentVersion->document->trashed() && $documentVersion->document->is_active,
                422,
                'The evidence must reference an active Core Document Version.',
            );
        }

        return DB::transaction(function () use ($request, $engagement, $member, $attributes, $actor): AemsTeamSafeguardDeclaration {
            $current = AemsTeamSafeguardDeclaration::query()
                ->where('engagement_team_id', $member->id)
                ->where('declaration_type', $attributes['declarationType'])
                ->where('is_current_revision', true)
                ->lockForUpdate()
                ->latest('version_number')
                ->first();
            if ($current && $current->status === 'ACCEPTED' && ! ($attributes['revisionReason'] ?? null)) {
                throw ValidationException::withMessages(['revisionReason' => ['A correction to an accepted declaration requires a revision reason.']]);
            }
            if ($current) {
                AemsTeamSafeguardDeclaration::query()->whereKey($current->id)->update([
                    'is_current_revision' => false,
                    'updated_at' => now(),
                ]);
            }
            $version = (int) ($current?->version_number ?? 0) + 1;
            $declaration = AemsTeamSafeguardDeclaration::query()->create([
                'declaration_family_uuid' => $current?->declaration_family_uuid ?? (string) Str::uuid(),
                'audit_engagement_id' => $engagement->id,
                'engagement_team_id' => $member->id,
                'user_id' => $member->user_id,
                'declaration_type' => $attributes['declarationType'],
                'version_number' => $version,
                'supersedes_id' => $current?->id,
                'is_current_revision' => true,
                'outcome' => $attributes['outcome'],
                'statement' => $attributes['statement'],
                'mitigation_plan' => $attributes['mitigationPlan'] ?? null,
                'evidence_document_version_id' => $attributes['evidenceDocumentVersionId'] ?? null,
                'status' => 'SUBMITTED',
                'submitted_by' => $actor->id,
                'submitted_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->record($request, $engagement, 'aems.team.safeguard.declaration_submitted', $current?->toArray(), $declaration->toArray(), $declaration);
            $this->notifications->teamSafeguard($request, $engagement, $member, 'SUBMITTED', $declaration);

            return $declaration->fresh(['user', 'submitter']);
        }, 3);
    }

    public function reviewDeclaration(
        Request $request,
        AuditEngagement $engagement,
        EngagementTeam $member,
        AemsTeamSafeguardDeclaration $declaration,
        array $attributes,
    ): AemsTeamSafeguardDeclaration {
        $this->ensureMember($engagement, $member);
        abort_unless(
            (int) $declaration->audit_engagement_id === (int) $engagement->id
                && (int) $declaration->engagement_team_id === (int) $member->id
                && $declaration->is_current_revision,
            404,
            'The safeguard declaration was not found for this assignment.',
        );
        abort_unless($declaration->status === 'SUBMITTED', 409, 'Only submitted declarations can be reviewed.');
        $actor = $request->user();
        // Users may review their own submission only when the explicit
        // self-review permission has been granted. Everyone else remains
        // subject to separation of duties.
        if (! $actor->hasPermission('aems.review.own_submission')
            && ((int) $actor->id === (int) $declaration->user_id
                || (int) $actor->id === (int) $declaration->submitted_by)) {
            throw ValidationException::withMessages(['reviewer' => ['The declaration must be reviewed independently.']]);
        }
        if ($attributes['decision'] === 'RETURN' && mb_strlen(trim((string) ($attributes['reviewNotes'] ?? ''))) < 5) {
            throw ValidationException::withMessages(['reviewNotes' => ['A return explanation is required.']]);
        }

        return DB::transaction(function () use ($request, $engagement, $declaration, $attributes, $actor): AemsTeamSafeguardDeclaration {
            $locked = AemsTeamSafeguardDeclaration::query()->lockForUpdate()->findOrFail($declaration->id);
            $decision = $attributes['decision'];
            $locked->update([
                'status' => $decision === 'ACCEPT' ? 'ACCEPTED' : 'RETURNED',
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'review_notes' => $attributes['reviewNotes'] ?? null,
                'updated_by' => $actor->id,
                'lock_version' => $locked->lock_version + 1,
            ]);
            $this->record($request, $engagement, 'aems.team.safeguard.declaration_'.strtolower($decision), ['status' => 'SUBMITTED'], ['status' => $locked->status, 'reviewNotes' => $locked->review_notes], $locked);
            $this->notifications->teamSafeguard($request, $engagement, $locked->teamMember()->firstOrFail(), $decision, $locked);

            return $locked->fresh(['user', 'reviewer']);
        }, 3);
    }

    public function assess(Request $request, AuditEngagement $engagement): AemsTeamSafeguardAssessment
    {
        $evaluation = $this->evaluate($engagement);
        return DB::transaction(function () use ($request, $engagement, $evaluation): AemsTeamSafeguardAssessment {
            $current = AemsTeamSafeguardAssessment::query()
                ->where('audit_engagement_id', $engagement->id)
                ->where('is_current_revision', true)
                ->latest('version_number')
                ->lockForUpdate()
                ->first();
            if ($current) {
                AemsTeamSafeguardAssessment::query()->whereKey($current->id)->update([
                    'is_current_revision' => false,
                    'updated_at' => now(),
                ]);
            }
            $assessment = AemsTeamSafeguardAssessment::query()->create([
                'assessment_uuid' => (string) Str::uuid(),
                'audit_engagement_id' => $engagement->id,
                'version_number' => (int) ($current?->version_number ?? 0) + 1,
                'is_current_revision' => true,
                'status' => 'PENDING',
                'provider_mode' => $evaluation['provider']['mode'],
                'provider_status' => $evaluation['provider'],
                'reconciliation' => $evaluation['reconciliation'],
                'checks' => $evaluation['checks'],
                'blockers' => $evaluation['blockers'],
                'warnings' => $evaluation['warnings'],
                'supersedes_id' => $current?->id,
                'assessed_by' => $request->user()->id,
                'assessed_at' => now(),
                'lock_version' => 1,
            ]);
            $this->record($request, $engagement, 'aems.team.safeguard.assessed', $current?->toArray(), $assessment->toArray(), $assessment);
            return $assessment->fresh(['assessor']);
        }, 3);
    }

    public function approve(Request $request, AuditEngagement $engagement, ?string $comment = null): AemsTeamSafeguardAssessment
    {
        $current = AemsTeamSafeguardAssessment::query()
            ->where('audit_engagement_id', $engagement->id)
            ->where('is_current_revision', true)
            ->latest('version_number')
            ->firstOrFail();
        if ($current->status !== 'PENDING') {
            throw ValidationException::withMessages(['assessment' => ['A pending safeguard assessment is required before approval.']]);
        }
        if ((int) $current->assessed_by === (int) $request->user()->id) {
            throw ValidationException::withMessages(['assessment' => ['The assessment and final safeguard decision require separate actors.']]);
        }
        $evaluation = $this->evaluate($engagement);
        if ($evaluation['blockers'] !== []) {
            throw ValidationException::withMessages(['readiness' => collect($evaluation['blockers'])->pluck('message')->values()->all()]);
        }

        return DB::transaction(function () use ($request, $engagement, $current, $evaluation, $comment): AemsTeamSafeguardAssessment {
            AemsTeamSafeguardAssessment::query()->whereKey($current->id)->update([
                'is_current_revision' => false,
                'updated_at' => now(),
            ]);
            $approved = AemsTeamSafeguardAssessment::query()->create([
                'assessment_uuid' => (string) Str::uuid(),
                'audit_engagement_id' => $engagement->id,
                'version_number' => $current->version_number + 1,
                'is_current_revision' => true,
                'status' => 'APPROVED',
                'provider_mode' => $evaluation['provider']['mode'],
                'provider_status' => $evaluation['provider'],
                'reconciliation' => $evaluation['reconciliation'],
                'checks' => $evaluation['checks'],
                'blockers' => [],
                'warnings' => $evaluation['warnings'],
                'supersedes_id' => $current->id,
                'assessed_by' => $current->assessed_by,
                'assessed_at' => $current->assessed_at,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'decision_comment' => $comment,
                'lock_version' => 1,
            ]);
            $this->record($request, $engagement, 'aems.team.safeguard.approved', $current->toArray(), $approved->toArray(), $approved);
            $this->notifications->teamSafeguardDecision($request, $engagement, $approved);
            return $approved->fresh(['assessor', 'approver']);
        }, 3);
    }

    /** @return list<array<string, mixed>> */
    public function aggregateGate(AuditEngagement $engagement): array
    {
        $evaluation = $this->evaluate($engagement);
        if (($evaluation['provider']['mode'] ?? null) !== ConfigurableResourcePlanningGateway::ARMIS_AUTHORITATIVE
            && ($evaluation['provider']['configuredMode'] ?? null) !== ConfigurableResourcePlanningGateway::ARMIS_AUTHORITATIVE) {
            return [];
        }
        return array_map(fn (array $blocker): array => [
            'key' => 'teamSafeguard_'.$blocker['code'],
            'label' => $blocker['message'],
            'met' => false,
            'link' => 'team',
        ], $evaluation['blockers']);
    }

    /** @return array<string, mixed> */
    private function memberEvaluation(AuditEngagement $engagement, EngagementTeam $member, bool $authoritative): array
    {
        $start = $member->assigned_from ?? $engagement->planned_start_date;
        $end = $member->assigned_until ?? $engagement->planned_end_date;
        $blockers = [];
        $warnings = [];
        $year = $start?->year ?? now()->year;
        $skills = $this->resources->skills([$member->user_id])[$member->user_id] ?? [];
        $certifications = collect($skills)->filter(fn (array $skill): bool => filled($skill['credentialType'] ?? null))->values()->all();
        $available = round((float) $this->resources->capacityFor($year, $member->user_id), 2);
        $allocated = round((float) EngagementTeam::query()
            ->where('user_id', $member->user_id)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->whereHas('engagement', fn ($query) => $query
                ->activeForResourceConflicts()
                ->whereYear('planned_start_date', $year))
            ->sum('planned_person_days'), 2);
        $capacityMet = $allocated <= $available + 0.009;
        $capacity = [
            'available' => $available,
            'allocated' => $allocated,
            'remaining' => round($available - $allocated, 2),
            'met' => $capacityMet,
        ];
        if (! $capacityMet) {
            $this->block($blockers, 'CAPACITY_CONFLICT', "{$member->user?->name} exceeds available person-days.", ['userId' => $member->user_id, ...$capacity]);
        }

        $unavailability = ($start && $end)
            ? $this->resources->unavailability($member->user_id, $start, $end)
            : [];
        foreach ($unavailability as $period) {
            $this->block($blockers, 'LEAVE_TRAINING_CONFLICT', "{$member->user?->name} is unavailable during the assignment period.", [
                'userId' => $member->user_id,
                'type' => $period['typeLabel'] ?? $period['title'] ?? 'UNAVAILABLE',
                'startDate' => $period['startDate'] ?? null,
                'endDate' => $period['endDate'] ?? null,
            ]);
        }
        $overlap = ($start && $end) ? EngagementTeam::query()
            ->where('user_id', $member->user_id)
            ->where('audit_engagement_id', '<>', $engagement->id)
            ->where('is_active', true)
            ->whereNull('ended_at')
            ->whereDate('assigned_from', '<=', $end->toDateString())
            ->whereDate('assigned_until', '>=', $start->toDateString())
            ->whereHas('engagement', fn ($query) => $query->activeForResourceConflicts())
            ->with('engagement:id,engagement_code,title')
            ->first() : null;
        if ($overlap) {
            $this->block($blockers, 'WORKLOAD_OVERLAP', "{$member->user?->name} overlaps another active engagement.", [
                'userId' => $member->user_id,
                'engagementCode' => $overlap->engagement?->engagement_code,
            ]);
        }
        if ($authoritative && ! ArmisResourceProfile::query()->where('user_id', $member->user_id)->where('status', 'ACTIVE')->exists()) {
            $this->block($blockers, 'ARMIS_PROFILE_MISSING', "{$member->user?->name} has no active ARMIS resource profile.", ['userId' => $member->user_id]);
        }

        return [
            'teamMemberId' => $member->id,
            'userId' => $member->user_id,
            'user' => $member->user ? ['id' => $member->user->id, 'employeeId' => $member->user->employee_id, 'name' => $member->user->name] : null,
            'role' => $member->assignment_role_code,
            'plannedPersonDays' => (float) $member->planned_person_days,
            'actualPersonDays' => round((float) $this->resources->assignmentActualPersonDays($member), 2),
            'competencies' => $skills,
            'certifications' => $certifications,
            'capacity' => $capacity,
            'unavailability' => $unavailability,
            'overlap' => $overlap ? ['engagementCode' => $overlap->engagement?->engagement_code] : null,
            'blockers' => $blockers,
            'warnings' => $warnings,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function competencyChecks($requirements, $team, array $skillMap): array
    {
        return $requirements->map(function (array $requirement) use ($team, $skillMap): array {
            $specializationId = (int) ($requirement['specializationId'] ?? 0);
            $minimum = (string) ($requirement['minimumProficiency'] ?? 'INTERMEDIATE');
            $qualified = $team->filter(function (EngagementTeam $member) use ($specializationId, $minimum, $skillMap): bool {
                return collect($skillMap[$member->user_id] ?? [])->contains(fn (array $skill): bool => (int) ($skill['id'] ?? 0) === $specializationId
                    && (self::PROFICIENCY_RANKS[$skill['proficiencyLevel'] ?? 'BASIC'] ?? 0) >= (self::PROFICIENCY_RANKS[$minimum] ?? 0));
            })->count();
            $minimumAuditors = max(1, (int) ($requirement['minimumAuditors'] ?? 1));
            return [
                'key' => (string) ($requirement['code'] ?? $specializationId),
                'label' => (string) ($requirement['label'] ?? 'Required competency'),
                'specializationId' => $specializationId,
                'minimumProficiency' => $minimum,
                'minimumAuditors' => $minimumAuditors,
                'qualified' => $qualified,
                'met' => $qualified >= $minimumAuditors,
            ];
        })->values()->all();
    }

    /** @return array{missing: list<array<string, mixed>>, conflicts: list<array<string, mixed>>} */
    private function declarationChecks($team): array
    {
        $missing = [];
        $conflicts = [];
        foreach ($team as $member) {
            $current = AemsTeamSafeguardDeclaration::query()
                ->where('engagement_team_id', $member->id)
                ->where('is_current_revision', true)
                ->where('status', 'ACCEPTED')
                ->get()
                ->keyBy('declaration_type');
            foreach (AemsTeamSafeguardDeclaration::TYPES as $type) {
                $declaration = $current->get($type);
                if (! $declaration) {
                    $missing[] = [
                        'teamMemberId' => $member->id,
                        'userId' => $member->user_id,
                        'declarationType' => $type,
                        'message' => "{$member->user?->name} has no accepted {$type} declaration.",
                    ];
                } elseif ($declaration->outcome === 'CONFLICT') {
                    $conflicts[] = [
                        'teamMemberId' => $member->id,
                        'userId' => $member->user_id,
                        'declarationType' => $type,
                        'message' => "{$member->user?->name} has an unresolved {$type} conflict.",
                    ];
                } elseif ($declaration->outcome === 'DISCLOSED' && blank($declaration->mitigation_plan)) {
                    $conflicts[] = [
                        'teamMemberId' => $member->id,
                        'userId' => $member->user_id,
                        'declarationType' => $type,
                        'message' => "{$member->user?->name} disclosed {$type} without a mitigation plan.",
                    ];
                }
            }
        }
        return ['missing' => $missing, 'conflicts' => $conflicts];
    }

    /** @param array<string, mixed> $checks */
    private function check(array &$checks, array &$blockers, string $key, string $label, bool $met, string $code, array $metadata = []): void
    {
        $checks[$key] = ['label' => $label, 'met' => $met, ...$metadata];
        if (! $met) {
            $this->block($blockers, $code, $label.'.', $metadata);
        }
    }

    /** @param array<int, array<string, mixed>> $blockers */
    private function block(array &$blockers, string $code, string $message, array $metadata = []): void
    {
        $blockers[] = ['code' => $code, 'severity' => 'error', 'message' => $message, ...$metadata];
    }

    private function ensureMember(AuditEngagement $engagement, EngagementTeam $member): void
    {
        abort_unless(
            (int) $member->audit_engagement_id === (int) $engagement->id
                && $member->is_active
                && ! $member->ended_at,
            404,
            'The active team assignment was not found.',
        );
    }

    /** @return array<string, mixed> */
    private function declaration(AemsTeamSafeguardDeclaration $declaration): array
    {
        return [
            'id' => $declaration->id,
            'familyUuid' => $declaration->declaration_family_uuid,
            'engagementTeamId' => $declaration->engagement_team_id,
            'userId' => $declaration->user_id,
            'declarationType' => $declaration->declaration_type,
            'versionNumber' => $declaration->version_number,
            'isCurrentRevision' => (bool) $declaration->is_current_revision,
            'outcome' => $declaration->outcome,
            'statement' => $declaration->statement,
            'mitigationPlan' => $declaration->mitigation_plan,
            'evidenceDocumentVersionId' => $declaration->evidence_document_version_id,
            'status' => $declaration->status,
            'submittedBy' => $declaration->submitted_by,
            'submittedAt' => $declaration->submitted_at?->toISOString(),
            'reviewedBy' => $declaration->reviewed_by,
            'reviewedAt' => $declaration->reviewed_at?->toISOString(),
            'reviewNotes' => $declaration->review_notes,
        ];
    }

    /** @return array<string, mixed> */
    private function assessment(AemsTeamSafeguardAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'assessmentUuid' => $assessment->assessment_uuid,
            'versionNumber' => $assessment->version_number,
            'isCurrentRevision' => (bool) $assessment->is_current_revision,
            'status' => $assessment->status,
            'providerMode' => $assessment->provider_mode,
            'providerStatus' => $assessment->provider_status,
            'reconciliation' => $assessment->reconciliation,
            'checks' => $assessment->checks,
            'blockers' => $assessment->blockers,
            'warnings' => $assessment->warnings,
            'assessedBy' => $assessment->assessed_by,
            'assessedAt' => $assessment->assessed_at?->toISOString(),
            'approvedBy' => $assessment->approved_by,
            'approvedAt' => $assessment->approved_at?->toISOString(),
            'decisionComment' => $assessment->decision_comment,
        ];
    }

    private function record(Request $request, AuditEngagement $engagement, string $action, ?array $old, ?array $new, Model $subject): void
    {
        $subjectType = $subject instanceof AemsTeamSafeguardDeclaration ? 'TEAM_SAFEGUARD_DECLARATION' : 'TEAM_SAFEGUARD_ASSESSMENT';
        $family = $subject instanceof AemsTeamSafeguardDeclaration ? $subject->declaration_family_uuid : $subject->assessment_uuid;
        $this->support->event(
            $request,
            $engagement,
            str($action)->afterLast('.')->upper()->toString(),
            $engagement->status,
            $engagement->status,
            $old,
            $new,
            subjectType: $subjectType,
            subjectId: $subject->id,
            subjectVersion: (int) $subject->version_number,
            subjectCode: $family,
            subjectFamilyUuid: $family,
        );
        $this->support->audit($request, $action, $engagement, $old, $new, [
            'teamSafeguardSubjectType' => $subjectType,
            'teamSafeguardSubjectId' => $subject->id,
            'teamSafeguardVersion' => (int) $subject->version_number,
        ]);
    }
}
