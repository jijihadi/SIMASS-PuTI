<?php

use App\Models\User;
use Tests\TestCase;

/** @var TestCase $this */
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
