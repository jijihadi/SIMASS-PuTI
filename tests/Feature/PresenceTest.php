<?php

use App\Models\Overtime;
use App\Models\Presence;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** @var TestCase $this */
afterEach(function () {
    Carbon::setTestNow();
});

// =========================================================================
// 1. Portal & Module-Scoped Sidebar
// =========================================================================

test('guest cannot access portal', function () {
    $this->get('/portal')
        ->assertRedirect('/');
});

test('authenticated user can view portal page with presence card', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/portal')
        ->assertStatus(200)
        ->assertSee('Selamat Datang')
        ->assertSee('SIMASS Presensi')
        ->assertSee(route('presence.dashboard'));
});

test('presence dashboard displays back to portal link and scopes sidebar', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/presence/dashboard')
        ->assertStatus(200)
        ->assertSee('Kembali ke Portal Hub')
        ->assertSee('SIMASS Presensi')
        ->assertDontSee('TICKETING');
});

test('sidebar remains scoped to current module when visiting profile or settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['active_module' => 'Presensi'])
        ->get('/profile')
        ->assertStatus(200)
        ->assertSee('SIMASS Presensi')
        ->assertDontSee('TICKETING');
});

test('sidebar renders module-specific accent color', function () {
    $user = User::factory()->create();

    // Presensi -> Red
    $this->actingAs($user)
        ->get('/presence/dashboard')
        ->assertStatus(200)
        ->assertSee('data-module="presensi"', false)
        ->assertSee('--sidebar-accent: #b6252a', false);

    // Inventaris -> Purple
    $this->actingAs($user)
        ->get('/inventory')
        ->assertStatus(200)
        ->assertSee('data-module="inventaris"', false)
        ->assertSee('--sidebar-accent: #7c3aed', false)
        ->assertSee('Inventaris PuTI');

    // Ticketing -> Emerald
    $this->actingAs($user)
        ->get('/ticket')
        ->assertStatus(200)
        ->assertSee('data-module="ticketing"', false)
        ->assertSee('--sidebar-accent: #059669', false)
        ->assertSee('e-Ticket PuTI');
});

// =========================================================================
// 2. Auto-Checkout & PDF Rounding Consistency
// =========================================================================

test('automatically checks out at 16:30 and records early overtime', function () {
    Carbon::setTestNow('2026-09-28 16:31:00');
    $user = User::factory()->create();
    $presence = Presence::create([
        'user_id' => $user->id,
        'tanggal' => '2026-09-28',
        'jam_masuk' => '08:00:00',
        'hari' => 'Senin',
    ]);

    $this->artisan('presence:auto-checkout')->assertSuccessful();

    expect($presence->fresh())
        ->jam_pulang->toBe('16:30:00')
        ->total_jam->toBe('8 Jam 30 Menit')
        ->menit_tambahan->toBe(30);

    expect(Overtime::where('presence_id', $presence->id)->first())
        ->durasi_menit->toBe(30)
        ->sisa_menit->toBe(30);
});

test('does not check out before 16:30', function () {
    Carbon::setTestNow('2026-09-28 16:29:00');
    $user = User::factory()->create();
    $presence = Presence::create([
        'user_id' => $user->id,
        'tanggal' => '2026-09-28',
        'jam_masuk' => '08:30:00',
        'hari' => 'Senin',
    ]);

    $this->artisan('presence:auto-checkout')->assertSuccessful();

    expect($presence->fresh()->jam_pulang)->toBeNull();
});

test('uses the configured checkout time', function () {
    Setting::create(['key' => 'jam_masuk', 'value' => '09:00']);
    Setting::create(['key' => 'jam_pulang', 'value' => '17:00']);
    Carbon::setTestNow('2026-09-28 17:01:00');
    $user = User::factory()->create();
    $presence = Presence::create([
        'user_id' => $user->id,
        'tanggal' => '2026-09-28',
        'jam_masuk' => '08:30:00',
        'hari' => 'Senin',
    ]);

    $this->artisan('presence:auto-checkout')->assertSuccessful();

    expect($presence->fresh()->jam_pulang)->toBe('17:00:00')
        ->and(Overtime::where('presence_id', $presence->id)->value('durasi_menit'))->toBe(30);
});

test('presence list and PDF show the same rounded duration while database stays exact', function () {
    $user = User::factory()->create();
    Role::firstOrCreate(['name' => 'student-staff', 'guard_name' => 'web']);
    $user->assignRole('student-staff');
    $presence = Presence::create([
        'user_id' => $user->id,
        'tanggal' => '2026-09-28',
        'jam_masuk' => '08:30:00',
        'jam_pulang' => '14:00:00',
        'hari' => 'Senin',
    ]);
    $presence->recalculateTotalJam();

    $this->actingAs($user)
        ->get('/presence/list?start_date=2026-09-28&end_date=2026-09-28')
        ->assertSuccessful()
        ->assertSee('6 Jam');

    $this->actingAs($user)
        ->get('/export-pdf?filterNama='.$user->id.'&startDate=2026-09-28&endDate=2026-09-28')
        ->assertSuccessful()
        ->assertSee('6 Jam');

    expect($presence->fresh()->total_jam)->toBe('5 Jam 30 Menit');
});

// =========================================================================
// 3. Manual Presence Entry & Cycle Boundary
// =========================================================================

test('admin can add a manual presence for student staff', function () {
    Role::firstOrCreate(['name' => 'student-staff', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage-presence', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-presence');
    $staff = User::factory()->create();
    $staff->assignRole('student-staff');

    $this->actingAs($admin)->post('/presence/manual', [
        'user_id' => $staff->id,
        'tanggal' => '2026-09-28',
        'jam_masuk' => '08:00',
        'jam_pulang' => '12:30',
        'pekerjaan' => 'Lupa check-in',
    ])->assertSessionHasNoErrors();

    $presence = Presence::where('user_id', $staff->id)->first();
    expect($presence->hari)->toBe('Senin')
        ->and($presence->total_jam)->toBe('4 Jam 30 Menit');
});

test('manual presence rejects duplicate date', function () {
    Role::firstOrCreate(['name' => 'student-staff', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage-presence', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-presence');
    $staff = User::factory()->create();
    $staff->assignRole('student-staff');

    $payload = ['user_id' => $staff->id, 'tanggal' => '2026-09-28', 'jam_masuk' => '08:00'];

    $this->actingAs($admin)->post('/presence/manual', $payload);
    $this->actingAs($admin)->post('/presence/manual', $payload)->assertSessionHasErrors('tanggal');

    expect(Presence::count())->toBe(1);
});

test('presence list on the 31st stays in the current cycle', function () {
    Role::firstOrCreate(['name' => 'student-staff', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'manage-presence', 'guard_name' => 'web']);

    $admin = User::factory()->create();
    $admin->givePermissionTo('manage-presence');
    $staff = User::factory()->create();
    $staff->assignRole('student-staff');

    $this->actingAs($admin)->post('/presence/manual', [
        'user_id' => $staff->id,
        'tanggal' => '2026-10-28',
        'jam_masuk' => '08:00',
    ]);

    // addMonth() on Oct 31 overflowed to Dec 1, jumping to the December cycle.
    $this->travelTo('2026-10-31 10:00:00');

    $this->actingAs($admin)->get('/presence/list')
        ->assertOk()
        ->assertViewHas('startDate', '2026-10-16')
        ->assertViewHas('presensiData', fn ($rows) => count($rows) === 1);
});

test('user without manage-presence cannot add manual presence', function () {
    Role::firstOrCreate(['name' => 'student-staff', 'guard_name' => 'web']);
    $staff = User::factory()->create();
    $staff->assignRole('student-staff');

    $this->actingAs($staff)->post('/presence/manual', [
        'user_id' => $staff->id,
        'tanggal' => '2026-09-28',
        'jam_masuk' => '08:00',
    ])->assertForbidden();
});

// =========================================================================
// 4. Presence Update Validation
// =========================================================================

test('checkout time cannot be earlier than checkin time when editing presence', function () {
    $user = User::factory()->create();
    $presence = Presence::create([
        'user_id' => $user->id,
        'tanggal' => '2026-09-28',
        'jam_masuk' => '14:45:00',
        'jam_pulang' => '16:30:00',
        'hari' => 'Senin',
    ]);

    $this->actingAs($user)
        ->put('/presence/'.$presence->id, [
            'jam_masuk' => '14:45',
            'jam_pulang' => '11:30',
            'pekerjaan' => 'Pekerjaan hari ini',
        ])
        ->assertSessionHasErrors([
            'jam_pulang' => 'Jam pulang tidak boleh lebih kecil dari jam masuk.',
        ]);

    expect($presence->fresh()->jam_pulang)->toBe('16:30:00');
});

test('checkout time equal to or later than checkin time is accepted', function () {
    $user = User::factory()->create();
    $presence = Presence::create([
        'user_id' => $user->id,
        'tanggal' => '2026-09-28',
        'jam_masuk' => '14:45:00',
        'jam_pulang' => null,
        'hari' => 'Senin',
    ]);

    $this->actingAs($user)
        ->put('/presence/'.$presence->id, [
            'jam_masuk' => '14:45',
            'jam_pulang' => '15:30',
            'pekerjaan' => 'Pekerjaan hari ini',
        ])
        ->assertSessionHasNoErrors();

    expect($presence->fresh()->jam_pulang)->toBe('15:30');
});
