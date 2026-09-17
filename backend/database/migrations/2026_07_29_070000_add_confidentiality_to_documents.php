<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Adds confidentiality classification and generated document numbering. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('confidentiality_level_id')
                ->nullable()
                ->after('document_type_id')
                ->constrained('master_list_items')
                ->restrictOnDelete();
            $table->string('document_code', 80)->nullable()->unique()->after('id');
            $table->index(['confidentiality_level_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex(['confidentiality_level_id', 'is_active']);
            $table->dropConstrainedForeignId('confidentiality_level_id');
            $table->dropUnique(['document_code']);
            $table->dropColumn('document_code');
        });
    }
};
