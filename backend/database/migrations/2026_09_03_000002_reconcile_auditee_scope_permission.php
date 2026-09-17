<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['code' => 'access.auditee_scope'],
            [
                'name' => 'Auditee Scope Access',
                'module' => 'access',
                'action' => 'auditee_scope',
                'description' => 'Limits recipient access to records covered by the user\'s auditee office scope.',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $roleId = DB::table('roles')->where('code', 'auditee_representative')->value('id');
        $permissionId = DB::table('permissions')->where('code', 'access.auditee_scope')->value('id');
        if ($roleId && $permissionId) {
            DB::table('role_permission')->insertOrIgnore([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('code', 'access.auditee_scope')->value('id');
        if ($permissionId) {
            DB::table('role_permission')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
