<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Adds ARMIS-2A competency certification revisions and controlled review metadata. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('armis_competencies', function (Blueprint $table): void {
            $table->uuid('competency_family_uuid')->nullable()->after('id');
            $table->unsignedInteger('version_number')->default(1)->after('competency_id');
            $table->foreignId('supersedes_id')->nullable()->after('version_number')
                ->constrained('armis_competencies')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->after('status')->index();
            $table->string('credential_type', 80)->nullable()->after('proficiency_level');
            $table->string('credential_reference', 120)->nullable()->after('credential_type');
            $table->string('issuer', 200)->nullable()->after('credential_reference');
            $table->date('issued_at')->nullable()->after('issuer');
            $table->foreignId('submitted_by')->nullable()->after('verified_by')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->foreignId('reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('verification_notes')->nullable()->after('notes');
            $table->foreignId('created_by')->nullable()->after('verification_notes')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('armis_competencies', function (Blueprint $table): void {
            $table->dropUnique('armis_resource_competency_unique');
            $table->unique(
                ['competency_family_uuid', 'version_number'],
                'armis_competency_family_version_unique',
            );
            $table->index(
                ['resource_profile_id', 'competency_id', 'is_current_revision'],
                'armis_competency_current_lookup_idx',
            );
        });

        DB::table('armis_competencies')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $row): void {
                DB::table('armis_competencies')
                    ->where('id', $row->id)
                    ->update([
                        'competency_family_uuid' => (string) Str::uuid(),
                        'version_number' => 1,
                        'is_current_revision' => true,
                    ]);
            });

        // PostgreSQL partial uniqueness protects the one-current-revision invariant.
        DB::statement(
            'CREATE UNIQUE INDEX armis_competency_current_unique ON armis_competencies '
            . '(resource_profile_id, competency_id) '
            . 'WHERE is_current_revision = TRUE AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS armis_competency_current_unique');

        Schema::table('armis_competencies', function (Blueprint $table): void {
            $table->dropIndex('armis_competency_current_lookup_idx');
            $table->dropUnique('armis_competency_family_version_unique');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropColumn([
                'competency_family_uuid', 'version_number', 'supersedes_id', 'is_current_revision',
                'credential_type', 'credential_reference', 'issuer', 'issued_at', 'submitted_by',
                'submitted_at', 'reviewed_by', 'reviewed_at', 'verification_notes',
            ]);
            $table->unique(
                ['resource_profile_id', 'competency_id'],
                'armis_resource_competency_unique',
            );
        });
    }
};
