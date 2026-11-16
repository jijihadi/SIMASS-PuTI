<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** @var TestCase $this */
beforeEach(function () {
    Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'student-staff', 'guard_name' => 'web']);

    Permission::firstOrCreate(['name' => 'manage-roles', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage-users', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view-presence', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'access-admin', 'guard_name' => 'web']);
});

test('unauthorized user cannot view role permission page', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('staff');

    $this->actingAs($user)
        ->get('/role-permission')
        ->assertStatus(403);
});

test('admin with manage-roles can view role permission listing', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');
    $admin->givePermissionTo('manage-roles');

    $targetUser = User::factory()->create(['name' => 'Budi Santoso', 'is_active' => true]);
    $targetUser->assignRole('student-staff');

    $this->actingAs($admin)
        ->get('/role-permission')
        ->assertStatus(200)
        ->assertSee('Daftar Pengguna', false)
        ->assertSee('Budi Santoso')
        ->assertSee(route('role-permission.edit', $targetUser));
});

test('admin can view dedicated role permission edit page with 3 main roles and app categories', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');
    $admin->givePermissionTo('manage-roles');

    $targetUser = User::factory()->create(['name' => 'Siti Aisyah', 'is_active' => true]);
    $targetUser->assignRole('staff');

    $this->actingAs($admin)
        ->get(route('role-permission.edit', $targetUser))
        ->assertStatus(200)
        ->assertSee('Pengaturan Peran', false)
        ->assertSee('Siti Aisyah')
        ->assertSee('Student Staff')
        ->assertSee('Staff')
        ->assertSee('Super Admin')
        ->assertSee('Presensi PuTI')
        ->assertSee('Admin Console');
});

test('admin can update user main role and direct permissions', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');
    $admin->givePermissionTo('manage-roles');

    $targetUser = User::factory()->create(['is_active' => true]);
    $targetUser->assignRole('staff');

    $response = $this->actingAs($admin)
        ->post(route('role-permission.update', $targetUser), [
            'user_role' => 'student-staff',
            'user_permissions' => ['view-presence'],
        ]);

    $response->assertRedirect(route('role-permission'))
        ->assertSessionHas('success');

    $targetUser->refresh();
    expect($targetUser->hasRole('student-staff'))->toBeTrue()
        ->and($targetUser->hasPermissionTo('view-presence'))->toBeTrue();
});

test('admin can view and assign inventaris permissions to user', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');
    $admin->givePermissionTo('manage-roles');

    $targetUser = User::factory()->create(['name' => 'Fajar Pratama', 'is_active' => true]);
    $targetUser->assignRole('staff');

    // 1. Verify edit page displays active SIMASS Inventaris category with permissions
    $editRes = $this->actingAs($admin)->get(route('role-permission.edit', $targetUser));
    $editRes->assertOk();
    $editRes->assertSee('SIMASS Inventaris');
    $editRes->assertSee('Lihat Data Inventaris');
    $editRes->assertSee('Tambah &amp; Import Aset', false);
    $editRes->assertSee('Kelola Aset &amp; Mutasi', false);
    $editRes->assertSee('Kelola Maintenance');

    // 2. Assign inventory permissions
    $updateRes = $this->actingAs($admin)->post(route('role-permission.update', $targetUser), [
        'user_role' => 'staff',
        'user_permissions' => ['view-inventory', 'create-inventory', 'manage-maintenance'],
    ]);
    $updateRes->assertRedirect(route('role-permission'));

    $targetUser->refresh();
    expect($targetUser->hasDirectPermission('view-inventory'))->toBeTrue();
    expect($targetUser->hasDirectPermission('create-inventory'))->toBeTrue();
    expect($targetUser->hasDirectPermission('manage-maintenance'))->toBeTrue();
    expect($targetUser->hasDirectPermission('manage-inventory'))->toBeFalse();
});

test('admin can toggle user status using radio toggle status value', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');
    $admin->givePermissionTo('manage-users');

    $targetUser = User::factory()->create(['name' => 'Doni Saputra', 'is_active' => true]);

    // Deactivate using status=inactive
    $resDeactivate = $this->actingAs($admin)->patch("/admin/users/{$targetUser->id}/toggle-status", [
        'status' => 'inactive',
    ]);
    $resDeactivate->assertRedirect();
    expect($targetUser->fresh()->is_active)->toBeFalse();

    // Activate using status=active
    $resActivate = $this->actingAs($admin)->patch("/admin/users/{$targetUser->id}/toggle-status", [
        'status' => 'active',
    ]);
    $resActivate->assertRedirect();
    expect($targetUser->fresh()->is_active)->toBeTrue();
});
