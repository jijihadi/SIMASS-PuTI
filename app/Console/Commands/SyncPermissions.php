<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely register and synchronize all system roles and permissions without modifying user data.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔄 Memulai sinkronisasi peran (roles) dan hak akses (permissions)...');

        // Clear Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. All permissions grouped by module
        $permissionGroups = [
            'Presensi PuTI' => [
                'view-presence',
                'create-presence',
                'manage-presence',
                'view-presence-history',
                'manage-overtime',
                'view-holiday',
                'manage-holiday',
            ],
            'Admin Console' => [
                'access-admin',
                'manage-users',
                'manage-modules',
                'manage-master-data',
                'view-audit-logs',
                'view-roles',
                'manage-roles',
                'view-settings',
                'manage-settings',
            ],
            'SIMASS Inventaris' => [
                'view-inventory',
                'create-inventory',
                'manage-inventory',
                'manage-maintenance',
            ],
        ];

        $totalPermissions = 0;
        foreach ($permissionGroups as $group => $perms) {
            foreach ($perms as $perm) {
                Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
                $totalPermissions++;
            }
        }
        $this->info("✓ {$totalPermissions} permissions terverifikasi/terdaftar.");

        // 2. Roles and their assigned permissions
        $roleDefinitions = [
            'super-admin' => [
                'description' => 'Akses penuh ke semua modul dan fitur sistem',
                'permissions' => Permission::where('name', '!=', 'create-presence')->pluck('name')->all(),
            ],
            'unit-head' => [
                'description' => 'Kepala Unit / Pimpinan',
                'permissions' => [
                    'view-presence',
                    'manage-presence',
                    'manage-overtime',
                    'view-holiday',
                    'view-settings',
                    'view-inventory',
                ],
            ],
            'staff' => [
                'description' => 'Staf Tetap Operasional & Pengelola',
                'permissions' => [
                    'view-presence',
                    'manage-presence',
                    'view-presence-history',
                    'manage-overtime',
                    'view-holiday',
                    'manage-holiday',
                    'view-settings',
                    'view-inventory',
                    'create-inventory',
                    'manage-inventory',
                    'manage-maintenance',
                ],
            ],
            'student-staff' => [
                'description' => 'Asisten Mahasiswa Operasional',
                'permissions' => [
                    'view-presence',
                    'create-presence',
                    'view-presence-history',
                    'manage-overtime',
                    'view-holiday',
                    'view-inventory',
                    'create-inventory',
                    'manage-maintenance',
                ],
            ],
            'user' => [
                'description' => 'Pengguna Umum Portal',
                'permissions' => [],
            ],
        ];

        $tableData = [];

        foreach ($roleDefinitions as $roleName => $data) {
            /** @var Role $role */
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions']);

            $tableData[] = [
                'role' => $roleName,
                'perms_count' => count($data['permissions']),
                'desc' => $data['description'],
            ];
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->table(['Role', 'Jumlah Permissions', 'Keterangan'], $tableData);

        $this->newLine();
        $this->info('✅ Seluruh roles dan permissions berhasil disinkronisasi tanpa mengubah data pengguna.');

        return Command::SUCCESS;
    }
}
