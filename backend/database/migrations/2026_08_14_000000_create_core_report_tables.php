<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_report_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('report_code', 80)->index();
            $table->string('report_title');
            $table->string('source_query_version', 40)->default('CORE-1-v1');
            $table->jsonb('filters');
            $table->jsonb('scope_snapshot');
            $table->jsonb('result_snapshot');
            $table->unsignedInteger('row_count')->default(0);
            $table->string('result_checksum_sha256', 64);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->useCurrent()->index();
            $table->timestamps();
            $table->index(['report_code', 'generated_at'], 'core_report_run_code_date_idx');
        });

        Schema::create('core_report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('core_report_run_id')->constrained('core_report_runs')->restrictOnDelete();
            $table->string('format', 10);
            $table->unsignedInteger('version_number')->default(1);
            $table->string('file_name', 255);
            $table->string('storage_path', 500);
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('file_size');
            $table->string('checksum_sha256', 64);
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at')->useCurrent()->index();
            $table->timestamps();
            $table->unique(['core_report_run_id', 'format', 'version_number'], 'core_report_export_version_unique');
            $table->index(['core_report_run_id', 'format', 'generated_at'], 'core_report_export_run_format_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_report_exports');
        Schema::dropIfExists('core_report_runs');
    }
};
