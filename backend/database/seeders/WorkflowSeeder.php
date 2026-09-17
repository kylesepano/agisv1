<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\WorkflowDefinition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds reusable workflow definitions, steps, transitions, and module mappings.
 */
class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'code');
        $permissions = Permission::query()->pluck('id', 'code');

        $this->seedDefinition([
            'code' => 'IAP_ANNUAL_PLAN_APPROVAL',
            'name' => 'Annual Internal Audit Plan Approval',
            'moduleCode' => 'IAP',
            'subjectType' => 'IAP_ANNUAL_PLAN',
            'description' => 'Reusable approval pattern for submission, management review, return, approval, activation, and completion of an annual audit plan.',
            'steps' => [
                ['code' => 'DRAFT', 'name' => 'Draft', 'type' => 'START', 'role' => 'agis_user', 'sla' => 120],
                ['code' => 'PENDING_REVIEW', 'name' => 'Pending Review', 'type' => 'INTERMEDIATE', 'role' => 'cias_management', 'sla' => 72],
                ['code' => 'RETURNED', 'name' => 'Returned for Revision', 'type' => 'INTERMEDIATE', 'role' => 'agis_user', 'sla' => 120],
                ['code' => 'APPROVED', 'name' => 'Approved', 'type' => 'INTERMEDIATE', 'role' => 'cias_management', 'sla' => 48],
                ['code' => 'ACTIVE', 'name' => 'Active', 'type' => 'INTERMEDIATE', 'role' => 'cias_management', 'sla' => null],
                ['code' => 'COMPLETED', 'name' => 'Completed', 'type' => 'END', 'role' => null, 'sla' => null],
            ],
            'transitions' => [
                ['code' => 'SUBMIT', 'name' => 'Submit for review', 'from' => 'DRAFT', 'to' => 'PENDING_REVIEW', 'role' => null, 'permission' => 'iap.submit', 'comment' => false, 'separate' => false],
                ['code' => 'RETURN', 'name' => 'Return for revision', 'from' => 'PENDING_REVIEW', 'to' => 'RETURNED', 'role' => null, 'permission' => 'iap.review', 'comment' => true, 'separate' => false],
                ['code' => 'APPROVE', 'name' => 'Approve plan', 'from' => 'PENDING_REVIEW', 'to' => 'APPROVED', 'role' => null, 'permission' => 'iap.approve', 'comment' => false, 'separate' => true],
                ['code' => 'RESUBMIT', 'name' => 'Resubmit plan', 'from' => 'RETURNED', 'to' => 'PENDING_REVIEW', 'role' => null, 'permission' => 'iap.submit', 'comment' => false, 'separate' => false],
                ['code' => 'ACTIVATE', 'name' => 'Activate plan', 'from' => 'APPROVED', 'to' => 'ACTIVE', 'role' => null, 'permission' => 'iap.activate', 'comment' => false, 'separate' => false],
                ['code' => 'COMPLETE', 'name' => 'Complete plan', 'from' => 'ACTIVE', 'to' => 'COMPLETED', 'role' => null, 'permission' => 'iap.complete', 'comment' => true, 'separate' => false],
            ],
        ], $roles, $permissions);

        $this->seedDefinition([
            'code' => 'CORE_DOCUMENT_REVIEW',
            'name' => 'Controlled Document Review',
            'moduleCode' => 'CORE',
            'subjectType' => 'DOCUMENT',
            'description' => 'Review and publication pattern for controlled reference documents without overwriting approved file versions.',
            'steps' => [
                ['code' => 'PREPARATION', 'name' => 'Preparation', 'type' => 'START', 'role' => 'agis_admin', 'sla' => 72],
                ['code' => 'REVIEW', 'name' => 'For Review', 'type' => 'INTERMEDIATE', 'role' => 'agis_admin', 'sla' => 48],
                ['code' => 'PUBLISHED', 'name' => 'Published', 'type' => 'END', 'role' => null, 'sla' => null],
            ],
            'transitions' => [
                ['code' => 'SUBMIT', 'name' => 'Submit document', 'from' => 'PREPARATION', 'to' => 'REVIEW', 'role' => null, 'permission' => 'documents.update', 'comment' => false, 'separate' => false],
                ['code' => 'RETURN', 'name' => 'Return document', 'from' => 'REVIEW', 'to' => 'PREPARATION', 'role' => null, 'permission' => 'documents.review', 'comment' => true, 'separate' => false],
                ['code' => 'PUBLISH', 'name' => 'Publish document', 'from' => 'REVIEW', 'to' => 'PUBLISHED', 'role' => null, 'permission' => 'documents.approve', 'comment' => false, 'separate' => true],
            ],
        ], $roles, $permissions);
    }

    private function seedDefinition(array $data, $roles, $permissions): void
    {
        DB::transaction(function () use ($data, $roles, $permissions): void {
            $definition = WorkflowDefinition::query()->firstOrCreate(
                ['code' => $data['code'], 'version' => 1],
                [
                    'name' => $data['name'],
                    'module_code' => $data['moduleCode'],
                    'subject_type' => $data['subjectType'],
                    'description' => $data['description'],
                    'status' => 'PUBLISHED',
                    'is_active' => true,
                    'published_at' => now(),
                ],
            );
            if ($definition->steps()->exists()) {
                // Existing installations may have role metadata from an older
                // seed. It is descriptive only; transition authorization is
                // now driven exclusively by required permissions.
                foreach ($data['transitions'] as $transition) {
                    $definition->transitions()
                        ->where('code', $transition['code'])
                        ->update([
                            'actor_role_id' => null,
                            'required_permission_id' => $transition['permission']
                                ? $permissions[$transition['permission']]
                                : null,
                        ]);
                }
                return;
            }

            $stepIds = [];
            foreach ($data['steps'] as $index => $step) {
                $created = $definition->steps()->create([
                    'code' => $step['code'],
                    'name' => $step['name'],
                    'sequence' => $index + 1,
                    'step_type' => $step['type'],
                    'responsible_role_id' => $step['role'] ? $roles[$step['role']] : null,
                    'sla_hours' => $step['sla'],
                ]);
                $stepIds[$step['code']] = $created->id;
            }
            foreach ($data['transitions'] as $index => $transition) {
                $definition->transitions()->create([
                    'from_step_id' => $stepIds[$transition['from']],
                    'to_step_id' => $stepIds[$transition['to']],
                    'code' => $transition['code'],
                    'name' => $transition['name'],
                    'sequence' => $index + 1,
                    'actor_role_id' => $transition['role'] ? $roles[$transition['role']] : null,
                    'required_permission_id' => $transition['permission']
                        ? $permissions[$transition['permission']]
                        : null,
                    'requires_comment' => $transition['comment'],
                    'enforce_separation_of_duties' => $transition['separate'],
                    'is_active' => true,
                ]);
            }
        });
    }
}
