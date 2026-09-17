<?php

namespace Database\Seeders;

use App\Models\CmsAutomationRule;
use App\Models\CmsAutomationRuleVersion;
use Illuminate\Database\Seeder;

/** Seeds conservative, reviewable CMS automation defaults. */
class CmsAutomationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'rule_code' => 'CMS_TARGET_DATE_REMINDER',
                'name' => 'CMS target-date reminder',
                'description' => 'Notify the current monitor and responsible office when an open recommendation approaches its effective target date.',
                'rule_type' => CmsAutomationRule::TYPE_REMINDER,
                'configuration' => ['triggerCode' => 'TARGET_DATE', 'daysAhead' => 7],
            ],
            [
                'rule_code' => 'CMS_CLOSURE_READINESS_DAILY',
                'name' => 'CMS closure-readiness detection',
                'description' => 'Identify IMPLEMENTED recommendations that satisfy the existing formal closure readiness checklist.',
                'rule_type' => CmsAutomationRule::TYPE_CLOSURE_READINESS,
                'configuration' => ['triggerCode' => 'CLOSURE_READINESS'],
            ],
            [
                'rule_code' => 'CMS_OVERDUE_ESCALATION_CANDIDATE',
                'name' => 'CMS overdue escalation candidate',
                'description' => 'Create a reviewable escalation candidate for open recommendations materially overdue against the effective target date.',
                'rule_type' => CmsAutomationRule::TYPE_ESCALATION_CANDIDATE,
                'configuration' => ['triggerCode' => 'OVERDUE_TARGET_DATE', 'overdueDays' => 30, 'severityCode' => 'HIGH'],
            ],
        ];

        foreach ($rules as $attributes) {
            $rule = CmsAutomationRule::query()->firstOrCreate(
                ['rule_code' => $attributes['rule_code']],
                [
                    'name' => $attributes['name'],
                    'description' => $attributes['description'],
                    'rule_type' => $attributes['rule_type'],
                    'status_code' => CmsAutomationRule::ACTIVE,
                    'schedule_code' => 'DAILY',
                    'configuration' => $attributes['configuration'],
                    'lock_version' => 1,
                ],
            );

            if (! $rule->current_version_id) {
                $version = CmsAutomationRuleVersion::query()->create([
                    'cms_automation_rule_id' => $rule->id,
                    'version_number' => 1,
                    'status_code' => 'ACTIVE',
                    'configuration' => $attributes['configuration'],
                    'effective_from' => now(),
                ]);
                $rule->forceFill(['current_version_id' => $version->id])->save();
            }
        }
    }
}
