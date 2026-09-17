<?php

namespace Database\Seeders;

use App\Models\IapAuditUniverseItem;
use App\Models\IapPrioritizationRun;
use App\Models\IapRiskPeriod;
use App\Models\IapRiskPeriodCriterion;
use App\Models\IapRiskPeriodEvent;
use App\Models\IapUniverseRiskAssessment;
use App\Models\IapUniverseRiskScore;
use App\Models\MasterListItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds risk criteria, assessment periods, scores, and validation history.
 */
class IapRiskPeriodSeeder extends Seeder
{
    private const WEIGHTS = [
        'FINANCIAL_MATERIALITY' => 15,
        'PRIOR_FINDINGS' => 15,
        'CONTROL_MATURITY' => 15,
        'LEGAL_REGULATORY' => 10,
        'COMPLEXITY_CHANGE' => 10,
        'FRAUD_INTEGRITY' => 10,
        'PUBLIC_SERVICE_IMPACT' => 10,
        'TIME_SINCE_AUDIT' => 5,
        'MANAGEMENT_CONCERN' => 5,
        'IT_DATA_DEPENDENCY' => 5,
    ];

    public function run(): void
    {
        $renderSafe = (bool) config('demo.full_render_seeders');
        if (! $renderSafe) {
            IapSchedulingSeeder::clearDemoPlan();
        }
        $management = User::query()
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.manage_universe'))
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'aems.review.own_submission'))
            ->first();
        $auditor = User::query()
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.assign_team'))
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'aems.aeo.prepare'))
            ->when($management, fn ($query) => $query->where('id', '<>', $management->id))
            ->first();
        $validator = User::query()
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'users.view'))
            ->when($management, fn ($query) => $query->where('id', '<>', $management->id))
            ->when($auditor, fn ($query) => $query->where('id', '<>', $auditor->id))
            ->first();
        if (! $management || ! $auditor || ! $validator) {
            return;
        }

        $subjects = IapAuditUniverseItem::query()
            ->whereIn('subject_code', ['AU-REV-001', 'AU-PROC-001', 'AU-ICT-001'])
            ->orderBy('subject_code')->get();
        if ($subjects->isEmpty()) {
            return;
        }

        $criteria = MasterListItem::query()
            ->whereIn('code', array_keys(self::WEIGHTS))
            ->whereHas('masterList', fn ($query) => $query->where('code', 'IAP_RISK_CRITERION'))
            ->get()->keyBy('code');
        $levels = MasterListItem::query()
            ->whereIn('code', ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])
            ->whereHas('masterList', fn ($query) => $query->where('code', 'RISK_LEVEL'))
            ->get()->keyBy('code');

        foreach ([
            ['year' => 2025, 'status' => 'LOCKED', 'offset' => 0],
            ['year' => 2026, 'status' => 'OPEN', 'offset' => 1],
        ] as $cycle) {
            $existing = IapRiskPeriod::withTrashed()
                ->where('period_code', 'RISK-'.$cycle['year'])->first();
            if ($renderSafe && $existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                continue;
            }
            if ($existing) {
                IapPrioritizationRun::withTrashed()
                    ->where('risk_period_id', $existing->id)
                    ->get()
                    ->each
                    ->forceDelete();
            }
            $existing?->forceDelete();

            $period = IapRiskPeriod::query()->create([
                'period_code' => 'RISK-'.$cycle['year'],
                'name' => $cycle['year'].' Audit Universe Risk Assessment',
                'assessment_year' => $cycle['year'],
                'start_date' => $cycle['year'].'-01-02',
                'end_date' => $cycle['year'].'-03-31',
                'instructions' => 'Score every selected Audit Universe subject from 1 (Low) to 5 (Critical), explain the basis, record control effectiveness, and attach supporting evidence.',
                'status' => $cycle['status'],
                'created_by' => $management->id,
                'opened_at' => now()->subDays($cycle['status'] === 'LOCKED' ? 400 : 20),
                'opened_by' => $management->id,
                'submitted_at' => $cycle['status'] === 'LOCKED' ? now()->subDays(380) : null,
                'submitted_by' => $cycle['status'] === 'LOCKED' ? $management->id : null,
                'validated_at' => $cycle['status'] === 'LOCKED' ? now()->subDays(375) : null,
                'validated_by' => $cycle['status'] === 'LOCKED' ? $validator->id : null,
                'locked_at' => $cycle['status'] === 'LOCKED' ? now()->subDays(374) : null,
                'locked_by' => $cycle['status'] === 'LOCKED' ? $validator->id : null,
                'lock_version' => $cycle['status'] === 'LOCKED' ? 5 : 2,
                'is_active' => true,
            ]);

            foreach (self::WEIGHTS as $code => $weight) {
                IapRiskPeriodCriterion::query()->create([
                    'period_id' => $period->id,
                    'criterion_id' => $criteria[$code]->id,
                    'weight' => $weight,
                    'display_order' => array_search($code, array_keys(self::WEIGHTS), true) + 1,
                ]);
            }

            foreach ($subjects as $subjectIndex => $subject) {
                $scores = [];
                foreach (array_keys(self::WEIGHTS) as $criterionIndex => $code) {
                    $rating = min(5, max(1, 5 - (($subjectIndex + $criterionIndex + $cycle['offset']) % 3)));
                    $scores[$code] = $rating;
                }
                $inherent = round(collect($scores)
                    ->map(fn ($rating, $code) => $rating * self::WEIGHTS[$code] / 100)
                    ->sum(), 2);
                $control = 20 + ($subjectIndex * 10);
                $residual = round($inherent * (1 - $control / 100), 2);
                $assessment = IapUniverseRiskAssessment::query()->create([
                    'period_id' => $period->id,
                    'audit_universe_item_id' => $subject->id,
                    'assessed_by' => $auditor->id,
                    'assessment_date' => $cycle['year'].'-02-15',
                    'control_effectiveness_percent' => $control,
                    'inherent_risk_score' => $inherent,
                    'residual_risk_score' => $residual,
                    'inherent_risk_level_id' => $levels[$this->level($inherent)]->id,
                    'residual_risk_level_id' => $levels[$this->level($residual)]->id,
                    'control_effectiveness_notes' => 'Controls are documented but require consistent monitoring and evidence of supervisory review.',
                    'justification' => 'Assessment considers material exposure, prior assurance results, service impact, complexity, compliance sensitivity, and management concern.',
                    'evidence_summary' => 'Audit Universe profile, prior audit history, office reports, and available monitoring information.',
                    'status' => $cycle['status'] === 'LOCKED' ? 'LOCKED' : 'DRAFT',
                    'validated_by' => $cycle['status'] === 'LOCKED' ? $validator->id : null,
                    'validated_at' => $cycle['status'] === 'LOCKED' ? now()->subDays(375) : null,
                    'lock_version' => 1,
                ]);
                foreach ($scores as $code => $rating) {
                    IapUniverseRiskScore::query()->create([
                        'assessment_id' => $assessment->id,
                        'criterion_id' => $criteria[$code]->id,
                        'criterion_weight' => self::WEIGHTS[$code],
                        'rating' => $rating,
                        'weighted_score' => round($rating * self::WEIGHTS[$code] / 100, 2),
                        'comment' => 'Demo score based on the seeded Audit Universe risk profile.',
                    ]);
                }
            }

            $events = [['CREATE', null, 'DRAFT', $management, 1], ['OPEN', 'DRAFT', 'OPEN', $management, 2]];
            if ($cycle['status'] === 'LOCKED') {
                $events = [
                    ...$events,
                    ['SUBMIT', 'OPEN', 'PENDING_VALIDATION', $management, 3],
                    ['VALIDATE', 'PENDING_VALIDATION', 'VALIDATED', $validator, 4],
                    ['LOCK', 'VALIDATED', 'LOCKED', $validator, 5],
                ];
            }
            foreach ($events as [$action, $from, $to, $actor, $version]) {
                IapRiskPeriodEvent::query()->create([
                    'period_id' => $period->id,
                    'action' => $action,
                    'from_status' => $from,
                    'to_status' => $to,
                    'actor_id' => $actor->id,
                    'comment' => $action === 'CREATE' ? 'Seeded demonstration risk-assessment cycle.' : null,
                    'period_lock_version' => $version,
                ]);
            }
        }
    }

    private function level(float $score): string
    {
        return $score >= 4 ? 'CRITICAL' : ($score >= 3 ? 'HIGH' : ($score >= 2 ? 'MEDIUM' : 'LOW'));
    }
}
