<?php

namespace Database\Seeders;

use App\Models\AuditArea;
use App\Models\SiapObjective;
use App\Models\SiapPriority;
use App\Models\SiapWorkflowEvent;
use App\Models\StrategicInternalAuditPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a representative strategic plan, objectives, priorities, and history.
 */
class SiapSeeder extends Seeder
{
    public function run(): void
    {
        $renderSafe = (bool) config('demo.full_render_seeders');
        $management = User::query()->where('username', 'departmenthead')->first();
        $approver = User::query()->where('username', 'admin')->first();
        if (! $management || ! $approver) {
            return;
        }

        $plan = StrategicInternalAuditPlan::query()->updateOrCreate(
            ['plan_code' => 'SIAP-2026-2030-R00'],
            [
                'start_year' => 2026,
                'end_year' => 2030,
                'title' => '2026–2030 Strategic Internal Audit Plan',
                'strategic_context' => 'The city is expanding digital services, infrastructure, revenue programs, and public-service delivery while strengthening accountability and regulatory compliance.',
                'vision' => 'A trusted, independent, and risk-focused internal audit service that supports responsive and accountable city governance.',
                'mission_alignment' => 'Align internal-audit assurance and advisory work with the city development agenda, CIAS mandate, public accountability, and continuous service improvement.',
                'planning_methodology' => 'Risk-based planning using the Audit Universe, periodic risk assessment, prior audit results, management concerns, resource capacity, and applicable public-sector internal-audit standards.',
                'expected_outcomes' => 'Stronger revenue safeguards, improved procurement compliance, more resilient information systems, and better governance over priority city programs.',
                'status' => 'ACTIVE',
                'revision_number' => 0,
                'is_current_revision' => true,
                'prepared_by' => $management->id,
                'coordinator_id' => $management->id,
                'submitted_at' => now()->subDays(14),
                'submitted_by' => $management->id,
                'approved_at' => now()->subDays(10),
                'approved_by' => $approver->id,
                'activated_at' => now()->subDays(7),
                'activated_by' => $approver->id,
                'lock_version' => 4,
                'is_active' => true,
            ],
        );

        if (! $renderSafe) {
            $plan->objectives()->delete();
        }
        foreach ([
            [
                'code' => 'OBJ-1',
                'title' => 'Strengthen revenue collection controls',
                'description' => 'Provide risk-based assurance over major local revenue streams, collection systems, accountability, and reconciliation controls.',
                'outcome' => 'More complete, accurate, timely, and properly safeguarded city collections.',
                'areas' => ['REVENUE', 'FINANCIAL'],
            ],
            [
                'code' => 'OBJ-2',
                'title' => 'Improve information-technology governance',
                'description' => 'Assess digital governance, cybersecurity, privacy, availability, change management, and data integrity across critical city systems.',
                'outcome' => 'More secure, available, and reliable information systems supporting city services.',
                'areas' => ['ICT', 'GOVERNANCE'],
            ],
            [
                'code' => 'OBJ-3',
                'title' => 'Improve procurement compliance',
                'description' => 'Prioritize assurance over procurement planning, supplier selection, contracting, receiving, payment, and property-accountability controls.',
                'outcome' => 'Transparent, compliant, economical, and properly documented procurement.',
                'areas' => ['PROCUREMENT', 'COMPLIANCE'],
            ],
        ] as $index => $data) {
            $attributes = [
                'title' => $data['title'],
                'description' => $data['description'],
                'expected_outcome' => $data['outcome'],
                'display_order' => $index + 1,
            ];
            $objective = $renderSafe
                ? SiapObjective::query()->updateOrCreate(
                    ['strategic_plan_id' => $plan->id, 'objective_code' => $data['code']],
                    $attributes,
                )
                : SiapObjective::query()->create([
                    'strategic_plan_id' => $plan->id,
                    'objective_code' => $data['code'],
                    ...$attributes,
                ]);
            $objective->auditAreas()->sync(
                AuditArea::query()->whereIn('code', $data['areas'])->pluck('id'),
            );
        }

        if (! $renderSafe) {
            $plan->priorities()->delete();
        }
        foreach ([
            [
                'code' => 'PRI-1',
                'title' => 'Revenue assurance and financial sustainability',
                'theme' => 'Financial Stewardship',
                'description' => 'Direct recurring coverage toward material revenue, cash, disbursement, and financial-reporting exposure.',
                'outcome' => 'Reduced leakage and stronger fiscal accountability.',
            ],
            [
                'code' => 'PRI-2',
                'title' => 'Digital resilience and information governance',
                'theme' => 'Digital Governance',
                'description' => 'Provide assurance over critical systems, cyber risk, privacy, continuity, and data-dependent city services.',
                'outcome' => 'Resilient, secure, and trustworthy digital public services.',
            ],
            [
                'code' => 'PRI-3',
                'title' => 'Transparent procurement and service delivery',
                'theme' => 'Good Governance',
                'description' => 'Examine high-value procurement and priority frontline programs for compliance, economy, efficiency, and effectiveness.',
                'outcome' => 'Defensible procurement decisions and better public-service outcomes.',
            ],
        ] as $index => $data) {
            $attributes = [
                'title' => $data['title'],
                'theme' => $data['theme'],
                'description' => $data['description'],
                'expected_outcome' => $data['outcome'],
                'display_order' => $index + 1,
            ];
            if ($renderSafe) {
                SiapPriority::query()->updateOrCreate(
                    ['strategic_plan_id' => $plan->id, 'priority_code' => $data['code']],
                    $attributes,
                );
            } else {
                SiapPriority::query()->create([
                    'strategic_plan_id' => $plan->id,
                    'priority_code' => $data['code'],
                    ...$attributes,
                ]);
            }
        }

        if (! $renderSafe) {
            $plan->workflowEvents()->delete();
        }
        foreach ([
            ['CREATE', null, 'DRAFT', $management, 1, 'Initial strategic plan prepared.'],
            ['SUBMIT', 'DRAFT', 'PENDING_REVIEW', $management, 2, 'Submitted for formal CIAS review.'],
            ['APPROVE', 'PENDING_REVIEW', 'APPROVED', $approver, 3, 'Approved as the strategic direction for 2026–2030.'],
            ['ACTIVATE', 'APPROVED', 'ACTIVE', $approver, 4, 'Activated for annual risk-based planning.'],
        ] as [$action, $from, $to, $actor, $version, $comment]) {
            $attributes = [
                'actor_id' => $actor->id,
                'actor_role_code' => $actor->role->code,
                'comment' => $comment,
                'plan_lock_version' => $version,
                'metadata' => ['seeded' => true],
            ];
            if ($renderSafe) {
                SiapWorkflowEvent::query()->firstOrCreate(
                    [
                        'strategic_plan_id' => $plan->id,
                        'action' => $action,
                        'from_status' => $from,
                        'to_status' => $to,
                    ],
                    $attributes,
                );
            } else {
                SiapWorkflowEvent::query()->create([
                    'strategic_plan_id' => $plan->id,
                    'action' => $action,
                    'from_status' => $from,
                    'to_status' => $to,
                    ...$attributes,
                ]);
            }
        }
    }
}
