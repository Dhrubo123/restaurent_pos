<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();
        DB::table('roles')->insertOrIgnore([
            'key' => 'supervisor',
            'name' => 'Supervisor',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $roleId = DB::table('roles')->where('key', 'supervisor')->value('id');
        $permissionIds = DB::table('permissions')->whereIn('key', [
            'view-floor', 'manage-floor', 'manage-menu', 'manage-orders',
            'manage-kitchen', 'manage-bar', 'take-payment', 'refund-payment', 'view-reports',
        ])->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $roleId = DB::table('roles')->where('key', 'supervisor')->value('id');
        if ($roleId && ! DB::table('role_user')->where('role_id', $roleId)->exists()) {
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
