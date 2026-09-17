<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Adds multi-role assignments, lock state, login history, and account controls. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_manually_locked')->default(false)->index()->after('locked_until');
            $table->timestamp('manually_locked_at')->nullable()->after('is_manually_locked');
            $table->foreignId('manually_locked_by')
                ->nullable()
                ->after('manually_locked_at')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::create('user_role_assignments', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->boolean('is_primary')->default(false)->index();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->primary(['user_id', 'role_id']);
            $table->index(['role_id', 'is_primary']);
        });

        $now = now();
        DB::table('users')
            ->select(['id', 'role_id'])
            ->orderBy('id')
            ->each(function (object $user) use ($now): void {
                DB::table('user_role_assignments')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role_id' => $user->role_id,
                    'is_primary' => true,
                    'assigned_by' => null,
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manually_locked_by');
            $table->dropColumn(['is_manually_locked', 'manually_locked_at']);
        });
    }
};
