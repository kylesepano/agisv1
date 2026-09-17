<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds the immutable, approval-controlled CMS target-date extension aggregate. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_target_date_extension_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')
                ->constrained('cms_recommendation_cases')
                ->restrictOnDelete();
            $table->unsignedInteger('request_sequence');
            $table->date('baseline_effective_target_date');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->unsignedBigInteger('resolved_version_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['cms_recommendation_case_id', 'request_sequence'],
                'cms_extension_request_sequence_unique',
            );
            $table->index(
                ['cms_recommendation_case_id', 'resolved_at'],
                'cms_extension_request_case_resolution_idx',
            );
        });

        Schema::create('cms_target_date_extension_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_target_date_extension_request_id')
                ->constrained('cms_target_date_extension_requests')
                ->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->foreignId('previous_version_id')
                ->nullable()
                ->constrained('cms_target_date_extension_versions')
                ->restrictOnDelete();
            $table->string('status_code', 30)->default('DRAFT');
            $table->string('active_slot', 20)->nullable();
            $table->foreignId('accepted_action_plan_version_id')
                ->constrained('cms_action_plan_versions')
                ->restrictOnDelete();
            $table->foreignId('recorded_progress_update_version_id')
                ->nullable()
                ->constrained('cms_progress_update_versions')
                ->restrictOnDelete();
            $table->unsignedInteger('case_lock_version')->default(1);
            $table->date('requested_target_date');
            $table->text('extension_justification');
            $table->text('cause_of_delay');
            $table->text('actions_already_taken');
            $table->text('remaining_actions');
            $table->text('recovery_plan');
            $table->text('impact_if_not_approved');
            $table->text('revised_schedule_summary');
            $table->text('management_progress_summary')->nullable();
            $table->text('no_evidence_explanation')->nullable();
            $table->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('review_started_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_reason')->nullable();
            $table->text('revision_reason')->nullable();
            $table->jsonb('submission_snapshot')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();

            $table->unique(
                ['cms_target_date_extension_request_id', 'version_number'],
                'cms_extension_version_number_unique',
            );
            $table->index(
                ['cms_target_date_extension_request_id', 'status_code'],
                'cms_extension_version_status_idx',
            );
        });

        Schema::create('cms_target_date_extension_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_target_date_extension_version_id')
                ->constrained('cms_target_date_extension_versions')
                ->restrictOnDelete();
            $table->unique('cms_target_date_extension_version_id', 'cms_ext_assessment_version_unique');
            $table->foreignId('assessor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('recommendation_code', 40);
            $table->text('assessment_summary');
            $table->text('evidence_review_summary');
            $table->text('feasibility_assessment');
            $table->text('risk_of_delay_summary');
            $table->text('conditions_or_observations')->nullable();
            $table->timestamp('assessed_at');
            $table->timestamps();
        });

        Schema::create('cms_target_date_extension_decisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_target_date_extension_version_id')
                ->constrained('cms_target_date_extension_versions')
                ->restrictOnDelete();
            $table->unique('cms_target_date_extension_version_id', 'cms_ext_decision_version_unique');
            $table->string('decision_code', 30);
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->text('decision_comment');
            $table->text('override_reason')->nullable();
            $table->date('previous_effective_target_date');
            $table->date('approved_target_date')->nullable();
            $table->date('new_effective_target_date')->nullable();
            $table->timestamps();
        });

        Schema::create('cms_target_date_extension_evidence_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_target_date_extension_version_id')
                ->constrained('cms_target_date_extension_versions')
                ->restrictOnDelete();
            $table->foreignId('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->restrictOnDelete();
            $table->string('evidence_category', 80);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('source_or_custodian', 255)->nullable();
            $table->foreignId('linked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('linked_at');
            $table->string('checksum_sha256', 64);
            $table->foreignId('confidentiality_level_id')->constrained('master_list_items')->restrictOnDelete();
            $table->string('confidentiality_code_snapshot', 80);
            $table->foreignId('removed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->text('removal_reason')->nullable();
            $table->timestamps();

            $table->index(
                ['cms_target_date_extension_version_id', 'removed_at'],
                'cms_extension_evidence_active_idx',
            );
        });

        Schema::create('cms_recommendation_target_date_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cms_recommendation_case_id')
                ->constrained('cms_recommendation_cases')
                ->restrictOnDelete();
            $table->string('history_code', 40);
            $table->date('previous_target_date')->nullable();
            $table->date('new_target_date');
            $table->foreignId('cms_target_date_extension_decision_id')
                ->nullable()
                ->constrained('cms_target_date_extension_decisions')
                ->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                'cms_target_date_extension_decision_id',
                'cms_target_date_history_decision_unique',
            );
            $table->unique(
                ['cms_recommendation_case_id', 'history_code', 'new_target_date'],
                'cms_target_date_history_backfill_unique',
            );
            $table->index(
                ['cms_recommendation_case_id', 'occurred_at'],
                'cms_target_date_history_case_occurred_idx',
            );
        });

        Schema::table('cms_target_date_extension_requests', function (Blueprint $table): void {
            $table->foreign('current_version_id', 'cms_extension_current_version_fk')
                ->references('id')
                ->on('cms_target_date_extension_versions')
                ->restrictOnDelete();
            $table->foreign('resolved_version_id', 'cms_extension_resolved_version_fk')
                ->references('id')
                ->on('cms_target_date_extension_versions')
                ->restrictOnDelete();
        });

        $driver = DB::getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX cms_extension_one_unresolved_case_unique
                 ON cms_target_date_extension_requests (cms_recommendation_case_id)
                 WHERE resolved_at IS NULL',
            );
            DB::statement(
                "CREATE UNIQUE INDEX cms_extension_one_active_version_unique
                 ON cms_target_date_extension_versions (cms_target_date_extension_request_id)
                 WHERE status_code IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'FOR_APPROVAL')",
            );
        }

        if ($driver === 'pgsql') {
            DB::statement(
                "ALTER TABLE cms_target_date_extension_versions
                 ADD CONSTRAINT cms_extension_version_status_check
                 CHECK (status_code IN ('DRAFT', 'SUBMITTED', 'UNDER_REVIEW', 'RETURNED', 'FOR_APPROVAL', 'APPROVED', 'REJECTED'))",
            );
            DB::statement(
                "ALTER TABLE cms_target_date_extension_assessments
                 ADD CONSTRAINT cms_extension_assessment_recommendation_check
                 CHECK (recommendation_code IN ('RECOMMEND_APPROVAL', 'RECOMMEND_REJECTION'))",
            );
            DB::statement(
                "ALTER TABLE cms_target_date_extension_decisions
                 ADD CONSTRAINT cms_extension_decision_code_check
                 CHECK (decision_code IN ('APPROVED', 'REJECTED'))",
            );
            DB::statement(
                "ALTER TABLE cms_recommendation_target_date_history
                 ADD CONSTRAINT cms_target_date_history_code_check
                 CHECK (history_code IN ('INITIAL_TARGET', 'LEGACY_EFFECTIVE_TARGET', 'EXTENSION_APPROVED'))",
            );
        }

        $this->backfillTargetDateHistory();
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS cms_extension_one_active_version_unique');
            DB::statement('DROP INDEX IF EXISTS cms_extension_one_unresolved_case_unique');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS cms_extension_one_active_version_unique');
            DB::statement('DROP INDEX IF EXISTS cms_extension_one_unresolved_case_unique');
        }

        Schema::table('cms_target_date_extension_requests', function (Blueprint $table): void {
            $table->dropForeign('cms_extension_current_version_fk');
            $table->dropForeign('cms_extension_resolved_version_fk');
        });
        Schema::dropIfExists('cms_recommendation_target_date_history');
        Schema::dropIfExists('cms_target_date_extension_evidence_links');
        Schema::dropIfExists('cms_target_date_extension_decisions');
        Schema::dropIfExists('cms_target_date_extension_assessments');
        Schema::dropIfExists('cms_target_date_extension_versions');
        Schema::dropIfExists('cms_target_date_extension_requests');
    }

    private function backfillTargetDateHistory(): void
    {
        DB::table('cms_recommendation_cases')
            ->join(
                'cms_recommendations',
                'cms_recommendations.id',
                '=',
                'cms_recommendation_cases.cms_recommendation_id',
            )
            ->select([
                'cms_recommendation_cases.id as case_id',
                'cms_recommendation_cases.effective_target_implementation_date as effective_date',
                'cms_recommendations.original_target_implementation_date as original_date',
            ])
            ->orderBy('cms_recommendation_cases.id')
            ->get()
            ->each(function (object $case): void {
                $original = $case->original_date ?? $case->effective_date;
                if ($original) {
                    DB::table('cms_recommendation_target_date_history')->insertOrIgnore([
                        'cms_recommendation_case_id' => $case->case_id,
                        'history_code' => 'INITIAL_TARGET',
                        'previous_target_date' => null,
                        'new_target_date' => $original,
                        'actor_id' => null,
                        'occurred_at' => now(),
                        'metadata' => json_encode([
                            'source' => 'CMS-6A backfill',
                            'originalTargetDateAvailable' => $case->original_date !== null,
                        ]),
                        'created_at' => now(),
                    ]);
                }
                if ($case->effective_date
                    && $original
                    && (string) $case->effective_date !== (string) $original) {
                    DB::table('cms_recommendation_target_date_history')->insertOrIgnore([
                        'cms_recommendation_case_id' => $case->case_id,
                        'history_code' => 'LEGACY_EFFECTIVE_TARGET',
                        'previous_target_date' => $original,
                        'new_target_date' => $case->effective_date,
                        'actor_id' => null,
                        'occurred_at' => now(),
                        'metadata' => json_encode([
                            'source' => 'CMS-6A backfill',
                            'explanation' => 'Effective target predated CMS-6A and was not labelled an approved extension.',
                        ]),
                        'created_at' => now(),
                    ]);
                }
            });
    }
};
