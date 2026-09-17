<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_finding_procedure', function (Blueprint $table): void {
            $table->foreignId('audit_finding_id')
                ->constrained('audit_findings')
                ->cascadeOnDelete();
            $table->foreignId('audit_program_procedure_id')
                ->constrained('audit_program_procedures')
                ->restrictOnDelete();
            $table->text('criteria_reference')->nullable();
            $table->text('traceability_note')->nullable();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(
                ['audit_finding_id', 'audit_program_procedure_id'],
                'aem_finding_procedure_unique',
            );
            $table->index('audit_program_procedure_id', 'aem_finding_procedure_procedure_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_finding_procedure');
    }
};
