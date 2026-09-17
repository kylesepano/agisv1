<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Removes state-machine values that must remain controlled by application workflow rules. */
return new class extends Migration
{
    private const CODES = [
        'ENGAGEMENT_STATUS',
        'RECOMMENDATION_STATUS',
        'IAP_PLAN_STATUS',
        'IAP_APPROVAL_ACTION',
    ];

    public function up(): void
    {
        DB::table('master_lists')->whereIn('code', self::CODES)->delete();
    }

    public function down(): void
    {
        $now = now();

        foreach ($this->lists() as $list) {
            if (DB::table('master_lists')->where('code', $list['code'])->exists()) {
                continue;
            }

            $listId = DB::table('master_lists')->insertGetId([
                'code' => $list['code'],
                'name' => $list['name'],
                'description' => $list['description'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($list['items'] as $index => [$code, $label, $description]) {
                DB::table('master_list_items')->insert([
                    'master_list_id' => $listId,
                    'code' => $code,
                    'label' => $label,
                    'description' => $description,
                    'display_order' => $index + 1,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private function lists(): array
    {
        return [
            [
                'code' => 'ENGAGEMENT_STATUS',
                'name' => 'Engagement Status',
                'description' => 'Workflow states for audit engagements.',
                'items' => [
                    ['DRAFT', 'Draft', 'Being prepared by the assigned audit team.'],
                    ['PENDING_REVIEW', 'Pending for Review', 'Submitted to a reviewer.'],
                    ['RETURNED', 'Returned', 'Returned for revision or additional information.'],
                    ['APPROVED', 'Approved', 'Approved to proceed.'],
                    ['REJECTED', 'Rejected', 'Not approved to proceed.'],
                    ['PLANNING', 'Planning', 'Engagement planning is underway.'],
                    ['EXECUTION', 'Execution', 'Fieldwork and testing are underway.'],
                    ['REPORTING', 'Reporting', 'Results are being drafted or finalized.'],
                    ['COMPLETED', 'Completed', 'All planned engagement work is complete.'],
                    ['CLOSED', 'Closed', 'Engagement has been administratively closed.'],
                ],
            ],
            [
                'code' => 'RECOMMENDATION_STATUS',
                'name' => 'Recommendation Status',
                'description' => 'Lifecycle states for audit recommendations.',
                'items' => [
                    ['OPEN', 'Open', 'Recommendation has been issued and awaits action.'],
                    ['IN_PROGRESS', 'In Progress', 'Management action is underway.'],
                    ['FOR_VALIDATION', 'For Validation', 'Evidence is ready for CIAS validation.'],
                    ['IMPLEMENTED', 'Implemented', 'Required corrective action has been validated.'],
                    ['OVERDUE', 'Overdue', 'Target date has passed without validated completion.'],
                    ['CLOSED', 'Closed', 'Recommendation monitoring is complete.'],
                ],
            ],
            [
                'code' => 'IAP_PLAN_STATUS',
                'name' => 'IAP Plan Status',
                'description' => 'Controlled workflow statuses for Annual Internal Audit Plan revisions.',
                'items' => [
                    ['DRAFT', 'Draft', 'The plan revision is being prepared and remains editable.'],
                    ['PENDING_REVIEW', 'Pending Review', 'The plan has been submitted and is locked for review.'],
                    ['RETURNED_FOR_REVISION', 'Returned for Revision', 'The reviewer returned the plan with required changes.'],
                    ['RESUBMITTED', 'Resubmitted', 'A returned plan has been revised and submitted again.'],
                    ['APPROVED', 'Approved', 'The plan revision has been approved and is immutable.'],
                    ['ACTIVE', 'Active', 'The approved plan is authorized for implementation.'],
                    ['COMPLETED', 'Completed', 'Implementation of the annual plan has been formally completed.'],
                    ['REJECTED', 'Rejected', 'The submitted revision was rejected and is terminal.'],
                ],
            ],
            [
                'code' => 'IAP_APPROVAL_ACTION',
                'name' => 'IAP Approval Action',
                'description' => 'Controlled actions recorded in Annual Internal Audit Plan workflow history.',
                'items' => [
                    ['SUBMIT', 'Submit', 'Submit a Draft plan for formal review.'],
                    ['RETURN', 'Return', 'Return a submitted plan for required revision.'],
                    ['RESUBMIT', 'Resubmit', 'Submit a revised plan after addressing return instructions.'],
                    ['APPROVE', 'Approve', 'Approve the submitted plan revision.'],
                    ['REJECT', 'Reject', 'Reject the submitted plan revision.'],
                    ['ACTIVATE', 'Activate', 'Authorize implementation of an approved plan.'],
                    ['COMPLETE', 'Complete', 'Formally complete implementation of an active plan.'],
                    ['CREATE_REVISION', 'Create Revision', 'Create a new Draft revision from an approved or active plan.'],
                    ['ARCHIVE', 'Archive', 'Soft-delete an eligible plan revision.'],
                    ['RESTORE', 'Restore', 'Restore an eligible archived plan revision.'],
                ],
            ],
        ];
    }
};
