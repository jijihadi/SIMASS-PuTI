<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UnitHeadRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
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

        $this->command?->info('✅ Role `unit-head` dan seluruh hak aksesnya berhasil didaftarkan tanpa mengubah data lain.');
    }
}
