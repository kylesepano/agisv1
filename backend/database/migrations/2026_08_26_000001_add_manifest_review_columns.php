<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aems_completion_transfer_manifests', function (Blueprint $table): void {
            $table->foreignId('reviewed_by')->nullable()->after('reconciled_at')->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('review_comment')->nullable()->after('reconciliation_comment');
        });
    }

    public function down(): void
    {
        Schema::table('aems_completion_transfer_manifests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_comment']);
        });
    }
};
