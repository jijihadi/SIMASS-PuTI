<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $inventoryPermissions = [
            'view-inventory',
            'create-inventory',
            'manage-inventory',
            'manage-maintenance',
        ];

        foreach ($inventoryPermissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->givePermissionTo($inventoryPermissions);

        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $staff->givePermissionTo($inventoryPermissions);

        $studentStaff = Role::firstOrCreate(['name' => 'student-staff', 'guard_name' => 'web']);
        $studentStaff->givePermissionTo(['view-inventory', 'create-inventory', 'manage-maintenance']);

        $unithead = Role::firstOrCreate(['name' => 'unit-head', 'guard_name' => 'web']);
        $unithead->givePermissionTo(['view-inventory']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $inventoryPermissions = [
            'view-inventory',
            'create-inventory',
            'manage-inventory',
            'manage-maintenance',
        ];

        Permission::whereIn('name', $inventoryPermissions)->delete();
    }
};
