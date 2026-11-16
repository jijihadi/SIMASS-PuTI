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

        $permissions = [
            'view-presence',
            'manage-presence',
            'manage-overtime',
            'view-holiday',
            'view-settings',
            'view-inventory',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $unitHead = Role::firstOrCreate(['name' => 'unit-head', 'guard_name' => 'web']);
        $unitHead->syncPermissions($permissions);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $unitHead = Role::where('name', 'unit-head')->first();
        if ($unitHead) {
            $unitHead->syncPermissions([]);
            $unitHead->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
