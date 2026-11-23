<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** @var TestCase $this */

// =========================================================================
// 1. Guest & Authentication Routing Guards
// =========================================================================

test('root redirects to login page', function () {
    $this->get('/')
        ->assertRedirect('/login');
});

test('login page is accessible to guests', function () {
    $this->get('/login')
        ->assertStatus(200)
        ->assertSee('Username SSO');
});

test('unauthenticated user is redirected to landing page from protected routes', function () {
    $this->get('/presence/dashboard')
        ->assertRedirect('/');
});

test('authenticated user can access dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/presence/dashboard')
        ->assertStatus(200);
});

test('guest cannot access presence routes', function () {
    $this->get('/presence/')
        ->assertRedirect('/');

    $this->get('/presence/list')
        ->assertRedirect('/');

    $this->get('/presence/overtime')
        ->assertRedirect('/');
});

// =========================================================================
// 2. Login Throttling, Lockout Reset, Case-Insensitivity & Active Guard
// =========================================================================

test('login is throttled after 3 failed attempts and can be reset with reset-lockout route', function () {
    $user = User::factory()->create([
        'username' => 'testuser',
        'email' => 'testuser@example.com',
        'password' => Hash::make('correct-password'),
        'is_active' => true,
    ]);

    $throttleKey = 'login:testuser|127.0.0.1';
    RateLimiter::clear($throttleKey);

    $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'wrong-pass-1',
    ])->assertRedirect('/login')->assertSessionHasErrors(['username']);
    expect(RateLimiter::attempts($throttleKey))->toBe(1);

    $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'wrong-pass-2',
    ])->assertRedirect('/login');
    expect(RateLimiter::attempts($throttleKey))->toBe(2);

    $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'wrong-pass-3',
    ])->assertRedirect('/login');
    expect(RateLimiter::tooManyAttempts($throttleKey, 3))->toBeTrue();

    // 4th attempt even with correct password is blocked due to lockout
    $res4 = $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'correct-password',
    ]);
    $res4->assertRedirect('/login');
    $res4->assertSessionHasErrors(['username']);
    expect(session('errors')->first('username'))->toContain('Terlalu banyak percobaan login');

    // Reset lockout clears limiter
    $this->from('/login')->post('/login/reset-lockout', [
        'username' => 'testuser',
    ])->assertRedirect('/login')
        ->assertSessionHas('status', 'Kunci percobaan login berhasil direset. Silakan coba masuk kembali.');
    expect(RateLimiter::tooManyAttempts($throttleKey, 3))->toBeFalse();

    // Login succeeds after reset
    $this->from('/login')->post('/login', [
        'username' => 'testuser',
        'password' => 'correct-password',
    ])->assertRedirect('/portal');
    $this->assertAuthenticatedAs($user);
});

test('inactive staff or student staff cannot login even with correct password', function () {
    User::factory()->create([
        'username' => 'inactivestaff',
        'email' => 'inactive@example.com',
        'password' => Hash::make('password123'),
        'is_active' => false,
    ]);

    $this->from('/login')->post('/login', [
        'username' => 'inactivestaff',
        'password' => 'password123',
    ])->assertRedirect('/login')
        ->assertSessionHasErrors(['username' => 'Akun Anda berstatus nonaktif. Silakan hubungi administrator.']);
    $this->assertGuest();
});

test('login works case-insensitively with capital letter username or email', function () {
    $user = User::factory()->create([
        'name' => "Ma'ruf O'Connor",
        'username' => 'marufoconnor',
        'email' => 'maruf@student.telkomuniversity.ac.id',
        'password' => Hash::make('SecretPass123'),
        'is_active' => true,
    ]);

    $this->post('/login', [
        'username' => '  MarufOConnor ',
        'password' => 'SecretPass123',
    ])->assertRedirect('/portal');
    $this->assertAuthenticatedAs($user);

    $this->post('/logout');

    $this->post('/login', [
        'username' => 'MARUF@Student.TelkomUniversity.ac.id',
        'password' => 'SecretPass123',
    ])->assertRedirect('/portal');
    $this->assertAuthenticatedAs($user);
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

    $this->actingAs($admin)->patch("/presence/student-staff/{$student->id}/toggle-status")
        ->assertRedirect();
    expect($student->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)->patch("/presence/student-staff/{$student->id}/toggle-status");
    expect($student->fresh()->is_active)->toBeTrue();

    // Prevent self-deactivation
    $this->actingAs($admin)->patch("/presence/student-staff/{$admin->id}/toggle-status")
        ->assertSessionHasErrors(['error']);
    expect($admin->fresh()->is_active)->toBeTrue();

    User::factory()->create([
        'name' => 'Siti Aminah',
        'username' => 'siti123',
        'email' => 'siti@example.com',
        'type' => 'Student Staff',
        'position' => 'Web Developer',
        'is_active' => false,
    ]);

    $this->actingAs($admin)->get('/presence/student-staff?status=active')
        ->assertOk()
        ->assertSee('Budi Santoso')
        ->assertDontSee('Siti Aminah');

    $this->actingAs($admin)->get('/presence/student-staff?status=inactive')
        ->assertOk()
        ->assertSee('Siti Aminah')
        ->assertDontSee('Budi Santoso');
});
