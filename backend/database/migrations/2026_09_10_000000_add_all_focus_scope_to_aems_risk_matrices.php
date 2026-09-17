<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aems_risk_matrices', function (Blueprint $table): void {
            $table->boolean('all_audit_focuses')->default(false)->after('audit_focus_id');
        });
    }

    public function down(): void
    {
        Schema::table('aems_risk_matrices', function (Blueprint $table): void {
            $table->dropColumn('all_audit_focuses');
        });
    }
};
