<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Adds ARMIS-3A planning revisions, current guards, and audit metadata. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('armis_availability_periods', function (Blueprint $table): void {
            $table->uuid('availability_family_uuid')->nullable()->after('id');
            $table->unsignedInteger('version_number')->default(1)->after('resource_profile_id');
            $table->foreignId('supersedes_id')->nullable()->after('version_number')
                ->constrained('armis_availability_periods')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->after('status')->index();
            $table->foreignId('created_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('armis_capacity_submissions', function (Blueprint $table): void {
            $table->boolean('is_current_revision')->default(true)->after('status')->index();
            $table->foreignId('created_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('armis_workload_allocations', function (Blueprint $table): void {
            $table->uuid('workload_family_uuid')->nullable()->after('id');
            $table->unsignedInteger('version_number')->default(1)->after('resource_profile_id');
            $table->foreignId('supersedes_id')->nullable()->after('version_number')
                ->constrained('armis_workload_allocations')->nullOnDelete();
            $table->boolean('is_current_revision')->default(true)->after('status')->index();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        DB::table('armis_availability_periods')->orderBy('id')->get(['id'])->each(function (object $row): void {
            DB::table('armis_availability_periods')->where('id', $row->id)->update([
                'availability_family_uuid' => (string) Str::uuid(),
                'version_number' => 1,
                'is_current_revision' => true,
            ]);
        });
        DB::table('armis_workload_allocations')->orderBy('id')->get(['id'])->each(function (object $row): void {
            DB::table('armis_workload_allocations')->where('id', $row->id)->update([
                'workload_family_uuid' => (string) Str::uuid(),
                'version_number' => 1,
                'is_current_revision' => true,
            ]);
        });

        DB::statement(
            'CREATE UNIQUE INDEX armis_availability_current_unique ON armis_availability_periods '
            . '(resource_profile_id, availability_type, start_date, end_date) '
            . 'WHERE is_current_revision = TRUE AND deleted_at IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX armis_capacity_current_approved_unique ON armis_capacity_submissions '
            . '(resource_profile_id, fiscal_year) '
            . 'WHERE is_current_revision = TRUE AND status IN (\'APPROVED\', \'LOCKED\') AND deleted_at IS NULL',
        );
        DB::statement(
            'CREATE UNIQUE INDEX armis_workload_current_unique ON armis_workload_allocations '
            . '(resource_profile_id, requirement_id, source_module, source_type, source_id, fiscal_year) '
            . 'WHERE is_current_revision = TRUE AND deleted_at IS NULL',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS armis_workload_current_unique');
        DB::statement('DROP INDEX IF EXISTS armis_capacity_current_approved_unique');
        DB::statement('DROP INDEX IF EXISTS armis_availability_current_unique');

        Schema::table('armis_workload_allocations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropColumn(['workload_family_uuid', 'version_number', 'is_current_revision']);
        });
        Schema::table('armis_capacity_submissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('is_current_revision');
        });
        Schema::table('armis_availability_periods', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('supersedes_id');
            $table->dropColumn(['availability_family_uuid', 'version_number', 'is_current_revision']);
        });
    }
};
