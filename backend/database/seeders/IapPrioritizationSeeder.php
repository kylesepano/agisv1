<?php

namespace Database\Seeders;

use App\Models\IapPrioritizationEvent;
use App\Models\IapPrioritizationItem;
use App\Models\IapPrioritizationRun;
use App\Models\IapRiskPeriod;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a finalized ranking linked to validated risk-assessment data.
 */
class IapPrioritizationSeeder extends Seeder
{
    public function run(): void
    {
        $renderSafe = (bool) config('demo.full_render_seeders');
        $management = User::query()
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'iap.manage_universe'))
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'aems.review.own_submission'))
            ->first();
        $finalizer = User::query()
            ->whereHas('roles.permissions', fn ($permission) => $permission->where('code', 'users.view'))
            ->when($management, fn ($query) => $query->where('id', '<>', $management->id))
            ->first();
        $period = IapRiskPeriod::query()
            ->where('period_code', 'RISK-2025')
            ->where('status', 'LOCKED')
            ->first();
        if (! $management || ! $finalizer || ! $period) {
            return;
        }

        $existingRun = IapPrioritizationRun::withTrashed()
            ->where('run_code', 'PRIO-2025')
            ->first();
        if ($renderSafe && $existingRun) {
            if ($existingRun->trashed()) {
                $existingRun->restore();
            }
            return;
        }
        if ($existingRun) {
            $existingRun->forceDelete();
        }

        $run = IapPrioritizationRun::query()->create([
            'run_code' => 'PRIO-2025',
            'name' => '2025 Audit Universe Prioritization',
            'risk_period_id' => $period->id,
            'methodology' => 'Residual risk is converted to a 0–100 priority score. Subjects are ranked by residual risk, inherent risk, and subject name. High and Critical subjects are recommended for selection; Medium subjects are recommended for deferral; Low subjects are not selected.',
            'status' => 'FINALIZED',
            'created_by' => $management->id,
            'submitted_at' => now()->subDays(370),
            'submitted_by' => $management->id,
            'finalized_at' => now()->subDays(369),
            'finalized_by' => $finalizer->id,
            'lock_version' => 3,
            'is_active' => true,
        ]);

        $assessments = $period->assessments()
            ->where('status', 'LOCKED')
            ->with([
                'auditUniverseItem.responsibleOffice:id,code,name',
                'auditUniverseItem.primaryAuditArea:id,code,name',
                'residualRiskLevel',
            ])
            ->get()
            ->sort(function ($left, $right): int {
                return [$right->residual_risk_score, $right->inherent_risk_score, $left->auditUniverseItem->name]
                    <=> [$left->residual_risk_score, $left->inherent_risk_score, $right->auditUniverseItem->name];
            })
            ->values();

        foreach ($assessments as $index => $assessment) {
            $score = round((float) $assessment->residual_risk_score * 20, 2);
            $recommended = $score >= 60
                ? 'SELECTED'
                : ($score >= 40 ? 'DEFERRED' : 'NOT_SELECTED');
            IapPrioritizationItem::query()->create([
                'prioritization_run_id' => $run->id,
                'risk_assessment_id' => $assessment->id,
                'audit_universe_item_id' => $assessment->audit_universe_item_id,
                'subject_code' => $assessment->auditUniverseItem->subject_code,
                'subject_name' => $assessment->auditUniverseItem->name,
                'office_code' => $assessment->auditUniverseItem->responsibleOffice?->code,
                'office_name' => $assessment->auditUniverseItem->responsibleOffice?->name,
                'audit_area_code' => $assessment->auditUniverseItem->primaryAuditArea?->code,
                'audit_area_name' => $assessment->auditUniverseItem->primaryAuditArea?->name,
                'inherent_risk_score' => $assessment->inherent_risk_score,
                'control_effectiveness_percent' => $assessment->control_effectiveness_percent,
                'residual_risk_score' => $assessment->residual_risk_score,
                'risk_level_code' => $assessment->residualRiskLevel->code,
                'risk_level_label' => $assessment->residualRiskLevel->label,
                'priority_score' => $score,
                'system_rank' => $index + 1,
                'final_rank' => $index + 1,
                'recommended_decision' => $recommended,
                'decision' => $recommended,
                'decision_reason' => in_array($recommended, ['DEFERRED', 'NOT_SELECTED'], true)
                    ? 'Scheduled coverage is deferred in favor of higher residual-risk subjects and will be reconsidered in the next planning cycle.'
                    : null,
                'is_manual_override' => false,
                'lock_version' => 1,
            ]);
        }

        foreach ([
            ['CREATE', null, 'DRAFT', $management, 1],
            ['SUBMIT', 'DRAFT', 'PENDING_REVIEW', $management, 2],
            ['FINALIZE', 'PENDING_REVIEW', 'FINALIZED', $finalizer, 3],
        ] as [$action, $from, $to, $actor, $version]) {
            IapPrioritizationEvent::query()->create([
                'prioritization_run_id' => $run->id,
                'action' => $action,
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actor->id,
                'comment' => $action === 'FINALIZE'
                    ? 'Finalized demonstration ranking for annual audit-plan preparation.'
                    : null,
                'run_lock_version' => $version,
            ]);
        }
    }
}
