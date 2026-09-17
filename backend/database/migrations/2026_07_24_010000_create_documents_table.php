<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Creates the initial governed document repository metadata. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_type_id')->constrained('master_list_items')->restrictOnDelete();
            $table->string('title');
            $table->string('reference_number', 120)->nullable()->index();
            $table->string('issuing_authority')->nullable();
            $table->date('publication_date')->nullable()->index();
            $table->string('version', 60)->nullable();
            $table->text('description')->nullable();
            $table->string('original_file_name');
            $table->string('storage_path')->unique();
            $table->string('mime_type', 150);
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('checksum_sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['document_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
