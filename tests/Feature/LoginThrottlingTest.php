<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;

test('login is throttled after 3 failed attempts and can be reset with reset-lockout route', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'email' => 'testuser@example.com',
        'password' => Hash::make('correct-password'),
        'is_active' => true,
    ]);

    $throttleKey = 'login:testuser|127.0.0.1';
    RateLimiter::clear($throttleKey);

    // Attempt 1: wrong password
    $res1 = $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'wrong-pass-1',
    ]);
    $res1->assertRedirect('/login');
    $res1->assertSessionHasErrors(['username']);
    expect(RateLimiter::attempts($throttleKey))->toBe(1);

    // Attempt 2: wrong password
    $res2 = $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'wrong-pass-2',
    ]);
    $res2->assertRedirect('/login');
    expect(RateLimiter::attempts($throttleKey))->toBe(2);

    // Attempt 3: wrong password -> should trigger lockout
    $res3 = $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'wrong-pass-3',
    ]);
    $res3->assertRedirect('/login');
    expect(RateLimiter::tooManyAttempts($throttleKey, 3))->toBeTrue();

    // 4th attempt even with correct password is blocked due to lockout
    $res4 = $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'correct-password',
    ]);
    $res4->assertRedirect('/login');
    $res4->assertSessionHasErrors(['username']);
    expect(session('errors')->first('username'))->toContain('Terlalu banyak percobaan login');

    // Now hit reset-lockout route
    $resReset = $this->from('/login')->post('/login/reset-lockout', [
        'username' => 'testuser',
    ]);
    $resReset->assertRedirect('/login');
    $resReset->assertSessionHas('status', 'Kunci percobaan login berhasil direset. Silakan coba masuk kembali.');
    expect(RateLimiter::tooManyAttempts($throttleKey, 3))->toBeFalse();

    // Now login succeeds with correct password
    $resSuccess = $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'correct-password',
    ]);
    $resSuccess->assertRedirect('/portal');
    $this->assertAuthenticatedAs($user);
});

test('inactive staff or student staff cannot login even with correct password', function () {
    $inactiveUser = User::factory()->create([
        'username' => 'inactivestaff',
        'email' => 'inactive@example.com',
        'password' => Hash::make('password123'),
        'is_active' => false,
    ]);

    $res = $this->from('/login')->post('/login', [
        'username' => 'inactivestaff',
        'password' => 'password123',
    ]);

    $res->assertRedirect('/login');
    $res->assertSessionHasErrors(['username' => 'Akun Anda berstatus nonaktif. Silakan hubungi administrator.']);
    $this->assertGuest();
});

test('admin can toggle student staff active status and filter staff by status', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create([
        'type' => 'Staf',
        'position' => 'Admin PuTI',
        'is_active' => true,
    ]);
    $admin->assignRole('super-admin');

    $student = User::factory()->create([
        'name' => 'Budi Santoso',
        'username' => 'budi123',
        'email' => 'budi@example.com',
        'type' => 'Student Staff',
        'position' => 'Network Support',
        'is_active' => true,
    ]);

    // 1. Toggle status to inactive
    $toggleRes = $this->actingAs($admin)->patch("/presence/student-staff/{$student->id}/toggle-status");
    $toggleRes->assertRedirect();
    $student->refresh();
    expect($student->is_active)->toBeFalse();

    // 2. Toggle status back to active
    $this->actingAs($admin)->patch("/presence/student-staff/{$student->id}/toggle-status");
    $student->refresh();
    expect($student->is_active)->toBeTrue();

    // 3. Prevent self-deactivation
    $selfRes = $this->actingAs($admin)->patch("/presence/student-staff/{$admin->id}/toggle-status");
    $selfRes->assertSessionHasErrors(['error']);
    expect($admin->fresh()->is_active)->toBeTrue();

    // 4. Test status filter query in index
    $inactiveStudent = User::factory()->create([
        'name' => 'Siti Aminah',
        'username' => 'siti123',
        'email' => 'siti@example.com',
        'type' => 'Student Staff',
        'position' => 'Web Developer',
        'is_active' => false,
    ]);

    $resActive = $this->actingAs($admin)->get('/presence/student-staff?status=active');
    $resActive->assertOk();
    $resActive->assertSee('Budi Santoso');
    $resActive->assertDontSee('Siti Aminah');

    $resInactive = $this->actingAs($admin)->get('/presence/student-staff?status=inactive');
    $resInactive->assertOk();
    $resInactive->assertSee('Siti Aminah');
    $resInactive->assertDontSee('Budi Santoso');
});
