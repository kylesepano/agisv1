<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds immutable document versions and polymorphic module record links. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('version_label', 60)->nullable();
            $table->text('change_summary');
            $table->string('original_file_name');
            $table->string('storage_path')->unique();
            $table->string('mime_type', 150);
            $table->string('file_extension', 20)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->string('checksum_sha256', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index(['document_id', 'checksum_sha256']);
        });

        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignId('current_version_id')
                ->nullable()
                ->after('library_visible')
                ->constrained('document_versions')
                ->nullOnDelete();
        });

        Schema::create('document_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('module_code', 20)->index();
            $table->string('record_type', 40);
            $table->unsignedBigInteger('record_id')->default(0);
            $table->string('record_code', 80)->nullable();
            $table->string('record_label');
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['document_id', 'module_code', 'record_type', 'record_id'],
                'document_module_record_unique',
            );
            $table->index(['module_code', 'record_type', 'record_id']);
        });

        DB::table('documents')
            ->orderBy('id')
            ->each(function (object $document): void {
                $versionId = DB::table('document_versions')->insertGetId([
                    'document_id' => $document->id,
                    'version_number' => 1,
                    'version_label' => $document->version,
                    'change_summary' => 'Initial version migrated from the document repository.',
                    'original_file_name' => $document->original_file_name,
                    'storage_path' => $document->storage_path,
                    'mime_type' => $document->mime_type,
                    'file_extension' => $document->file_extension,
                    'file_size' => $document->file_size,
                    'checksum_sha256' => $document->checksum_sha256,
                    'uploaded_by' => $document->uploaded_by,
                    'created_at' => $document->created_at,
                    'updated_at' => $document->created_at,
                ]);

                DB::table('documents')
                    ->where('id', $document->id)
                    ->update(['current_version_id' => $versionId]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');

        Schema::table('documents', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_version_id');
        });

        Schema::dropIfExists('document_versions');
    }
};
