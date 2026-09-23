<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('audit_engagements', fn (Blueprint $table) => $table->json('audit_type_ids')->nullable()->after('audit_type_id')); } public function down(): void { Schema::table('audit_engagements', fn (Blueprint $table) => $table->dropColumn('audit_type_ids')); } };
