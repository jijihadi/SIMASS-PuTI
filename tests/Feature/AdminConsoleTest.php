<?php

use App\Models\AuditLog;
use App\Models\CampusLocation;
use App\Models\PortalModule;
use App\Models\ServiceCatalog;
use App\Models\Unit;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Http\UploadedFile;
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

// =========================================================================
// 1. Models & Relationships
// =========================================================================

test('user supports soft delete, active state, and unit assignment', function () {
    $unit = Unit::create(['name' => 'Direktorat PuTI', 'code' => 'PUTI']);
    $user = User::factory()->create([
        'unit_id' => $unit->id,
        'is_active' => true,
    ]);

    expect($user->unit->name)->toBe('Direktorat PuTI')
        ->and($user->is_active)->toBeTrue();

    $user->delete();
    expect(User::find($user->id))->toBeNull()
        ->and(User::withTrashed()->find($user->id))->not->toBeNull();
});

test('campus locations support hierarchy', function () {
    $building = CampusLocation::create(['type' => 'building', 'name' => 'Gedung Panambulai', 'code' => 'PA']);
    $floor = CampusLocation::create(['parent_id' => $building->id, 'type' => 'floor', 'name' => 'Lantai 1']);

    expect($floor->parent->id)->toBe($building->id)
        ->and($building->children)->toHaveCount(1);
});

test('portal module evaluates role accessibility and ordered scope', function () {
    $adminModule = PortalModule::create([
        'code' => 'admin',
        'name' => 'Admin Console',
        'order' => 0,
        'allowed_roles' => ['super-admin'],
    ]);

    $publicModule = PortalModule::create([
        'code' => 'presensi',
        'name' => 'SIMASS Presensi',
        'order' => 1,
        'allowed_roles' => null,
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('super-admin');

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    expect($adminModule->isAccessibleBy($admin))->toBeTrue()
        ->and($adminModule->isAccessibleBy($staff))->toBeFalse()
        ->and($publicModule->isAccessibleBy($staff))->toBeTrue()
        ->and(PortalModule::ordered()->first()->code)->toBe('admin');
});

test('service catalog and audit log record correctly', function () {
    $catalog = ServiceCatalog::create([
        'category' => 'Jaringan',
        'name' => 'Koneksi Wi-Fi',
        'sla_resolution_hours' => 4,
    ]);

    $log = AuditLog::create([
        'action' => 'created',
        'model_type' => ServiceCatalog::class,
        'model_id' => $catalog->id,
        'new_values' => ['name' => 'Koneksi Wi-Fi'],
    ]);

    expect($catalog->sla_resolution_hours)->toBe(4)
        ->and($log->new_values['name'])->toBe('Koneksi Wi-Fi');
});

test('portal module returns curated color theme', function () {
    $module = PortalModule::create([
        'code' => 'test-mod',
        'name' => 'Test Module',
        'color' => 'cyan',
    ]);

    expect($module->getColorTheme()['label'])->toBe('Teal / Cyan')
        ->and($module->getColorTheme()['dot'])->toBe('bg-cyan-500');

    $defaultModule = PortalModule::create([
        'code' => 'default-mod',
        'name' => 'Default Module',
    ]);

    expect($defaultModule->getColorTheme()['label'])->toBe('Telkom Red');
});

// =========================================================================
// 2. Security Guardrails & Dashboard Access
// =========================================================================

test('guest is redirected away from admin dashboard', function () {
    $this->get('/admin/dashboard')
        ->assertRedirect('/');
});

test('non-admin user receives 403 on admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    $this->actingAs($user)
        ->get('/admin/dashboard')
        ->assertStatus(403);
});

test('inactive super-admin receives 403 on admin dashboard', function () {
    $admin = User::factory()->create(['is_active' => false]);
    $admin->assignRole('super-admin');

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertStatus(403);
});

test('active super-admin can access admin dashboard', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $this->actingAs($admin)
        ->get('/admin/dashboard')
        ->assertStatus(200)
        ->assertSee('Admin Console')
        ->assertSee('Total Pengguna');
});

test('user policy prevents self lockout and last super-admin destruction', function () {
    $policy = new UserPolicy;

    $admin1 = User::factory()->create(['is_active' => true]);
    $admin1->assignRole('super-admin');

    expect($policy->deactivate($admin1, $admin1))->toBeFalse()
        ->and($policy->delete($admin1, $admin1))->toBeFalse()
        ->and($policy->demote($admin1, $admin1))->toBeFalse();

    $staff = User::factory()->create(['is_active' => true]);
    $staff->assignRole('staff');
    expect($policy->deactivate($staff, $admin1))->toBeFalse()
        ->and($policy->delete($staff, $admin1))->toBeFalse();

    $admin2 = User::factory()->create(['is_active' => true]);
    $admin2->assignRole('super-admin');
    expect($policy->deactivate($admin2, $admin1))->toBeTrue()
        ->and($policy->delete($admin2, $admin1))->toBeTrue()
        ->and($policy->demote($admin2, $admin1))->toBeTrue();
});

test('auditable trait records model changes', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $this->actingAs($admin);

    $module = PortalModule::create([
        'code' => 'test-mod',
        'name' => 'Initial Module',
        'order' => 1,
    ]);

    expect(AuditLog::where('model_type', PortalModule::class)->where('action', 'created')->exists())->toBeTrue();

    $module->update(['name' => 'Renamed Module']);
    $updateLog = AuditLog::where('model_type', PortalModule::class)->where('action', 'updated')->latest('id')->first();

    expect($updateLog->old_values['name'])->toBe('Initial Module')
        ->and($updateLog->new_values['name'])->toBe('Renamed Module');
});

// =========================================================================
// 3. User Management
// =========================================================================

test('super admin can view user management index', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    User::factory()->create(['name' => 'Budi Santoso', 'username' => 'budis']);

    $this->actingAs($admin)
        ->get('/admin/users')
        ->assertStatus(200)
        ->assertSee('Manajemen Akun Pengguna')
        ->assertSee('Budi Santoso');
});

test('super admin can create a user with role and unit', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $this->actingAs($admin)->post('/admin/users', [
        'name' => 'Ahmad Teknisi',
        'username' => 'ahmadtek',
        'email' => 'ahmad@telkomuniversity.ac.id',
        'role' => 'staff',
        'type' => 'Staf',
        'position' => 'NOC Officer',
    ])->assertRedirect();

    $created = User::where('username', 'ahmadtek')->first();
    expect($created)->not->toBeNull()
        ->and($created->is_active)->toBeTrue()
        ->and($created->hasRole('staff'))->toBeTrue();
});

test('super admin can toggle active status of user', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('staff');

    $this->actingAs($admin)->patch("/admin/users/{$target->id}/toggle-status")
        ->assertRedirect();
    expect($target->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch("/admin/users/{$target->id}/toggle-status")
        ->assertRedirect();
    expect($target->fresh()->is_active)->toBeTrue();
});

test('admin can toggle user status using radio toggle status value', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');
    $admin->givePermissionTo('manage-users');

    $targetUser = User::factory()->create(['name' => 'Doni Saputra', 'is_active' => true]);

    $this->actingAs($admin)->patch("/admin/users/{$targetUser->id}/toggle-status", [
        'status' => 'inactive',
    ])->assertRedirect();
    expect($targetUser->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch("/admin/users/{$targetUser->id}/toggle-status", [
        'status' => 'active',
    ])->assertRedirect();
    expect($targetUser->fresh()->is_active)->toBeTrue();
});

test('super admin can import users from csv', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $csvContent = "name,username,email,type,position,role\n";
    $csvContent .= "Imported User One,impuser1,imp1@telkomuniversity.ac.id,Student Staff,Support,student-staff\n";
    $csvContent .= "Imported User Two,impuser2,imp2@telkomuniversity.ac.id,Staf,Admin,staff\n";

    $file = UploadedFile::fake()->createWithContent('users.csv', $csvContent);

    $this->actingAs($admin)->post('/admin/users/import-csv', [
        'file' => $file,
    ])->assertRedirect();

    expect(User::where('username', 'impuser1')->exists())->toBeTrue()
        ->and(User::where('username', 'impuser2')->exists())->toBeTrue();
});

test('super admin can soft delete a user', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('staff');

    $this->actingAs($admin)->delete("/admin/users/{$target->id}")
        ->assertRedirect();

    expect(User::find($target->id))->toBeNull()
        ->and(User::withTrashed()->find($target->id))->not->toBeNull();
});

test('username and email of a deleted user can be reused', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $target = User::factory()->create(['username' => 'reynanda', 'email' => 'rey@telkomuniversity.ac.id']);
    $target->assignRole('staff');

    $this->actingAs($admin)->delete("/admin/users/{$target->id}");

    $this->actingAs($admin)->post('/admin/users', [
        'name' => 'Reynanda',
        'username' => 'reynanda',
        'email' => 'rey@telkomuniversity.ac.id',
        'role' => 'staff',
    ])->assertSessionHasNoErrors();

    expect(User::where('username', 'reynanda')->exists())->toBeTrue();
});

// =========================================================================
// 4. Portal Module Management
// =========================================================================

test('non-admin user cannot access portal module management', function () {
    $user = User::factory()->create(['is_active' => true]);
    $user->assignRole('staff');

    $this->actingAs($user)
        ->get('/admin/modules')
        ->assertStatus(403);
});

test('super admin can access portal module management index', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    PortalModule::create([
        'code' => 'test-app',
        'name' => 'Aplikasi Uji Coba',
        'description' => 'Deskripsi modul uji coba',
        'credit' => 'Courtesy to Tester',
        'icon' => 'box',
        'status' => 'active',
        'order' => 1,
    ]);

    $this->actingAs($admin)
        ->get('/admin/modules')
        ->assertStatus(200)
        ->assertSee('Manajemen Kartu Modul Portal')
        ->assertSee('Aplikasi Uji Coba')
        ->assertSee('Courtesy to Tester');
});

test('super admin can create a new portal module', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $this->actingAs($admin)->post('/admin/modules', [
        'name' => 'Modul Penjaminan Mutu',
        'code' => 'mutu',
        'description' => 'Sistem monitoring SPMI Telkom University Surabaya.',
        'credit' => 'Courtesy to Tim SPMI',
        'icon' => 'award',
        'status' => 'coming_soon',
        'order' => 7,
        'route_name' => 'mutu.dashboard',
        'allowed_roles' => ['staff', 'super-admin'],
    ])->assertRedirect();

    $module = PortalModule::where('code', 'mutu')->first();
    expect($module)->not->toBeNull()
        ->and($module->name)->toBe('Modul Penjaminan Mutu')
        ->and($module->credit)->toBe('Courtesy to Tim SPMI')
        ->and($module->status)->toBe('coming_soon')
        ->and($module->order)->toBe(7)
        ->and($module->allowed_roles)->toBe(['staff', 'super-admin']);
});

test('super admin can update an existing portal module', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $module = PortalModule::create([
        'code' => 'eticket',
        'name' => 'e-Ticket PuTI',
        'description' => 'Deskripsi lama',
        'credit' => 'Old Credit',
        'icon' => 'ticket',
        'status' => 'active',
        'order' => 3,
    ]);

    $this->actingAs($admin)->put("/admin/modules/{$module->id}", [
        'name' => 'e-Ticket PuTI V2',
        'code' => 'eticket',
        'description' => 'Deskripsi baru yang diperbarui',
        'credit' => 'Courtesy to Reynanda Shaquile S.Kom',
        'icon' => 'life-buoy',
        'status' => 'active',
        'order' => 2,
        'route_name' => 'ticket.index',
    ])->assertRedirect();

    $updated = $module->fresh();
    expect($updated->name)->toBe('e-Ticket PuTI V2')
        ->and($updated->description)->toBe('Deskripsi baru yang diperbarui')
        ->and($updated->credit)->toBe('Courtesy to Reynanda Shaquile S.Kom')
        ->and($updated->icon)->toBe('life-buoy')
        ->and($updated->order)->toBe(2);
});

test('super admin can create a module with outer link', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $this->actingAs($admin)->post('/admin/modules', [
        'name' => 'Telegram Bot PuTI',
        'code' => 'tg-bot',
        'description' => 'Bot interaktif telegram.',
        'credit' => 'Courtesy to Dev Bot',
        'icon' => 'bot',
        'status' => 'active',
        'order' => 8,
        'link_type' => 'url',
        'url' => 'https://t.me/puti_telkom_bot',
    ])->assertRedirect();

    $module = PortalModule::where('code', 'tg-bot')->first();
    expect($module)->not->toBeNull()
        ->and($module->url)->toBe('https://t.me/puti_telkom_bot')
        ->and($module->route_name)->toBeNull()
        ->and($module->isExternalLink())->toBeTrue()
        ->and($module->getTargetUrl())->toBe('https://t.me/puti_telkom_bot');
});

test('super admin can delete a portal module', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $module = PortalModule::create([
        'code' => 'deprecated-mod',
        'name' => 'Modul Lama',
        'icon' => 'trash',
        'status' => 'maintenance',
        'order' => 99,
    ]);

    $this->actingAs($admin)->delete("/admin/modules/{$module->id}")
        ->assertRedirect();

    expect(PortalModule::where('code', 'deprecated-mod')->exists())->toBeFalse();
});

// =========================================================================
// 5. Master Data & Audit Logs
// =========================================================================

test('non-admin is forbidden from accessing master data and audit logs', function () {
    $staff = User::factory()->create(['is_active' => true]);
    $staff->assignRole('staff');

    $this->actingAs($staff)->get('/admin/master-data')->assertStatus(403);
    $this->actingAs($staff)->get('/admin/audit-logs')->assertStatus(403);
});

test('super admin can view master data page', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    Unit::create(['name' => 'Biro Akademik', 'code' => 'BAK']);
    CampusLocation::create(['name' => 'Gedung Rektorat', 'type' => 'building']);
    ServiceCatalog::create(['name' => 'Gangguan WiFi Kampus', 'category' => 'Network', 'sla_resolution_hours' => 4]);

    $this->actingAs($admin)
        ->get('/admin/master-data')
        ->assertStatus(200)
        ->assertSee('Data Master & SLA')
        ->assertSee('Biro Akademik')
        ->assertSee('Gedung Rektorat')
        ->assertSee('Gangguan WiFi Kampus');
});

test('super admin can store and delete unit', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $this->actingAs($admin)->post('/admin/units', [
        'name' => 'Fakultas Teknik',
        'code' => 'FT-01',
    ])->assertRedirect();

    $unit = Unit::where('code', 'FT-01')->first();
    expect($unit)->not->toBeNull()
        ->and($unit->name)->toBe('Fakultas Teknik');

    expect(AuditLog::where('action', 'unit.created')->where('model_id', $unit->id)->first())->not->toBeNull();

    $this->actingAs($admin)->delete("/admin/units/{$unit->id}")->assertRedirect();
    expect(Unit::find($unit->id))->toBeNull();
});

test('super admin can store and delete campus location', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $this->actingAs($admin)->post('/admin/locations', [
        'name' => 'Gedung A',
        'type' => 'building',
        'code' => 'G-A',
    ])->assertRedirect();

    $loc = CampusLocation::where('name', 'Gedung A')->first();
    expect($loc)->not->toBeNull()
        ->and($loc->type)->toBe('building');

    $this->actingAs($admin)->delete("/admin/locations/{$loc->id}")->assertRedirect();
    expect(CampusLocation::find($loc->id))->toBeNull();
});

test('super admin can store and delete service catalog', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('super-admin');

    $this->actingAs($admin)->post('/admin/services', [
        'category' => 'Hardware',
        'name' => 'Perbaikan Printer',
        'sla_resolution_hours' => 8,
    ])->assertRedirect();

    $service = ServiceCatalog::where('name', 'Perbaikan Printer')->first();
    expect($service)->not->toBeNull()
        ->and($service->sla_resolution_hours)->toBe(8);

    $this->actingAs($admin)->delete("/admin/services/{$service->id}")->assertRedirect();
    expect(ServiceCatalog::find($service->id))->toBeNull();
});

test('super admin can view and search audit logs', function () {
    $admin = User::factory()->create(['name' => 'Super Admin Test', 'is_active' => true]);
    $admin->assignRole('super-admin');

    AuditLog::create([
        'user_id' => $admin->id,
        'action' => 'test.action.sample',
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/audit-logs')
        ->assertStatus(200)
        ->assertSee('Audit Log Administratif')
        ->assertSee('test.action.sample');

    $this->actingAs($admin)
        ->get('/admin/audit-logs?search=test.action.sample')
        ->assertStatus(200)
        ->assertSee('test.action.sample');
});

// =========================================================================
// 6. Role & Permission Management
// =========================================================================

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

    $this->actingAs($admin)
        ->post(route('role-permission.update', $targetUser), [
            'user_role' => 'student-staff',
            'user_permissions' => ['view-presence'],
        ])
        ->assertRedirect(route('role-permission'))
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

    $this->actingAs($admin)->get(route('role-permission.edit', $targetUser))
        ->assertOk()
        ->assertSee('SIMASS Inventaris')
        ->assertSee('Lihat Data Inventaris')
        ->assertSee('Tambah &amp; Import Aset', false)
        ->assertSee('Kelola Aset &amp; Mutasi', false)
        ->assertSee('Kelola Maintenance');

    $this->actingAs($admin)->post(route('role-permission.update', $targetUser), [
        'user_role' => 'staff',
        'user_permissions' => ['view-inventory', 'create-inventory', 'manage-maintenance'],
    ])->assertRedirect(route('role-permission'));

    $targetUser->refresh();
    expect($targetUser->hasDirectPermission('view-inventory'))->toBeTrue()
        ->and($targetUser->hasDirectPermission('create-inventory'))->toBeTrue()
        ->and($targetUser->hasDirectPermission('manage-maintenance'))->toBeTrue()
        ->and($targetUser->hasDirectPermission('manage-inventory'))->toBeFalse();
});
