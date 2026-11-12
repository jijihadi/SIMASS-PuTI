<?php

use App\Enums\TicketStatus;
use App\Models\Presence;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Tests\TestCase;

/** @var TestCase $this */
test('guest cannot access ticketing routes', function () {
    $this->get('/ticket')->assertRedirect('/');
    $this->get('/ticket/create')->assertRedirect('/');
    $this->get('/ticket/my-tickets')->assertRedirect('/');
});

test('authenticated user can view ticketing index and see categories', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/ticket')
        ->assertStatus(200)
        ->assertSee('Helpdesk PuTI')
        ->assertSee('Buat Tiket Baru')
        ->assertSee('Tiket Saya');
});

test('user can submit valid local ticket and ticket number is generated', function () {
    $user = User::factory()->create();

    $payload = [
        'category' => 'Jaringan & Internet',
        'service_item' => 'Wi-Fi Kampus (Eduroam / TelU-Guest)',
        'title' => 'Koneksi Wi-Fi Sering Terputus di Perpustakaan',
        'description' => 'Sinyal eduroam sering putus setiap 5 menit ketika menggunakan laptop di area sofa perpustakaan lantai 2.',
        'location' => 'Gedung Perpustakaan Lt. 2',
        'priority' => 'high',
    ];

    $response = $this->actingAs($user)->post('/ticket', $payload);

    $ticket = Ticket::where('title', $payload['title'])->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->ticket_number)->toStartWith('TIC-');
    expect($ticket->scope)->toBe('internal_surabaya');
    expect($ticket->status)->toBe(TicketStatus::Open->value);
    expect($ticket->user_status_label)->toBe('Diterima');
    expect($ticket->authority)->toBe('local');

    $response->assertRedirect(route('ticket.show', $ticket));
});

test('submitting central authority ticket routes to redirected and pauses SLA', function () {
    $user = User::factory()->create();

    $payload = [
        'category' => 'Akun & Akses Terpadu',
        'service_item' => 'Akun iGracias & Single Sign-On (SSO)',
        'title' => 'Akun iGracias Terkunci Setelah Salah Password',
        'description' => 'Mohon bantuan untuk reset atau buka blokir akun iGracias saya karena salah memasukkan password lebih dari batas wajar.',
        'location' => null,
        'priority' => 'medium',
    ];

    $this->actingAs($user)->post('/ticket', $payload);

    $ticket = Ticket::where('title', $payload['title'])->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->authority)->toBe('central');
    expect($ticket->scope)->toBe('escalated_central');
    expect($ticket->status)->toBe(TicketStatus::Redirected->value);
    expect($ticket->user_status_label)->toBe('Dialihkan ke Pusat');
    expect($ticket->sla_paused_at)->not->toBeNull();

    // Ensure Luna posted an informative message
    $lunaMsg = $ticket->messages()->where('sender_type', 'ai_luna')->first();
    expect($lunaMsg)->not->toBeNull();
    expect($lunaMsg->message)->toContain('sistem terpusat');
});

test('submitting ambiguous local ticket stays open with needs_clarification scope and pauses SLA', function () {
    $user = User::factory()->create();

    $payload = [
        'category' => 'Jaringan & Internet',
        'service_item' => 'Wi-Fi Kampus (Eduroam / TelU-Guest)',
        'title' => 'Wi-Fi Rusak',
        'description' => 'wifi mati', // too short & missing location
        'location' => '',
        'priority' => 'low',
    ];

    $this->actingAs($user)->post('/ticket', $payload);

    $ticket = Ticket::where('title', $payload['title'])->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->scope)->toBe('needs_clarification');
    expect($ticket->status)->toBe(TicketStatus::Open->value);
    expect($ticket->needsClarification())->toBeTrue();
    expect($ticket->user_status_label)->toBe('Diterima');
    expect($ticket->sla_paused_at)->not->toBeNull();

    // Ensure Luna posted a clarification request
    $lunaMsg = $ticket->messages()->where('sender_type', 'ai_luna')->first();
    expect($lunaMsg)->not->toBeNull();
    expect($lunaMsg->message)->toContain('Gedung');
});

test('user reply on open ticket with needs_clarification restores internal scope and resumes sla', function () {
    $user = User::factory()->create();

    $ticket = Ticket::factory()->create([
        'user_id' => $user->id,
        'scope' => 'needs_clarification',
        'status' => TicketStatus::Open->value,
        'sla_paused_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($user)
        ->post("/ticket/{$ticket->id}/reply", [
            'message' => 'Lokasi saya di Gedung A Lantai 2 R.204, SSID TelU-Guest tidak muncul.',
        ])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Open->value);
    expect($ticket->scope)->toBe('internal_surabaya');
    expect($ticket->sla_paused_at)->toBeNull();
});

test('technician can manually override ticket scope between local and central', function () {
    $staff = User::factory()->create(['type' => 'Staf']);
    $user = User::factory()->create();

    $ticket = Ticket::factory()->inProgress()->create([
        'user_id' => $user->id,
        'title' => 'Access Point Rusak',
        'description' => 'Lampu AP mati total di Ruang B301.',
    ]);

    // 1. Override to central escalation
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/override-scope", [
            'scope' => 'escalated_central',
            'central_ticket_ref' => 'PST-9988',
            'override_notes' => 'Ternyata controller AP di-manage langsung oleh tim Pusat Bandung.',
        ])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->scope)->toBe('escalated_central');
    expect($ticket->status)->toBe(TicketStatus::Redirected->value);
    expect($ticket->central_ticket_ref)->toBe('PST-9988');

    // 2. Override back to local
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/override-scope", [
            'scope' => 'internal_surabaya',
            'override_notes' => 'Ternyata PoE switch lokal Surabaya yang mati listrik, bukan controller pusat.',
        ])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->scope)->toBe('internal_surabaya');
    expect($ticket->status)->toBe(TicketStatus::InProgress->value);
    expect($ticket->sla_due_at)->not->toBeNull();
});

test('staff can transition through valid statuses and reopen failed ticket', function () {
    $staff = User::factory()->create(['type' => 'Staf']);
    $ticket = Ticket::factory()->open()->create();

    // open -> in_progress
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::InProgress->value,
        ])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::InProgress->value);
    expect($ticket->assigned_to)->toBe($staff->id);

    // in_progress -> failed (requires status_note)
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::Failed->value,
        ])
        ->assertSessionHasErrors('status_note');

    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::Failed->value,
            'status_note' => 'Switch pengganti masih menunggu pengadaan.',
        ])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Failed->value);
    expect($ticket->resolved_at)->not->toBeNull();

    // failed -> in_progress (reopened)
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::InProgress->value,
        ])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::InProgress->value);
    expect($ticket->resolved_at)->toBeNull();

    // in_progress -> finished
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::Finished->value,
        ])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Finished->value);
    expect($ticket->resolved_at)->not->toBeNull();

    // finished is final -> cannot transition back to in_progress
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::InProgress->value,
        ])
        ->assertSessionHasErrors('status');
});

test('staff canceling ticket to another campus unit requires freetext transferred_unit', function () {
    $staff = User::factory()->create(['type' => 'Staf']);
    $ticket = Ticket::factory()->inProgress()->create();

    // Missing transferred_unit when cancel_reason is transferred_unit
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::Canceled->value,
            'cancel_reason' => 'transferred_unit',
        ])
        ->assertSessionHasErrors('transferred_unit');

    // Valid freetext unit
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::Canceled->value,
            'cancel_reason' => 'transferred_unit',
            'transferred_unit' => 'Bagian Logistik & Sarpras Kampus',
            'status_note' => 'Kerusakan AC ruang server merupakan wewenang Sarpras.',
        ])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Canceled->value);
    expect($ticket->cancel_reason)->toBe('transferred_unit');
    expect($ticket->transferred_unit)->toBe('Bagian Logistik & Sarpras Kampus');
    expect($ticket->resolved_at)->not->toBeNull();
});

test('reporter can self-cancel ticket while open but not while in_progress', function () {
    $user = User::factory()->create();
    $openTicket = Ticket::factory()->open()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch("/ticket/{$openTicket->id}/cancel", [
            'status_note' => 'Sudah bisa konek setelah ganti kabel sendiri.',
        ])
        ->assertSessionHasNoErrors();

    $openTicket->refresh();
    expect($openTicket->status)->toBe(TicketStatus::Canceled->value);
    expect($openTicket->cancel_reason)->toBe('user_withdrawn');
    expect($openTicket->resolved_at)->not->toBeNull();

    // Cannot self-cancel when in_progress
    $inProgressTicket = Ticket::factory()->inProgress()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->patch("/ticket/{$inProgressTicket->id}/cancel", [
            'status_note' => 'Mau batalin',
        ])
        ->assertSessionHasErrors('status');

    $inProgressTicket->refresh();
    expect($inProgressTicket->status)->toBe(TicketStatus::InProgress->value);
});

test('luna live suggestion and chat endpoints return valid json responses', function () {
    $user = User::factory()->create();

    // Suggestion
    $this->actingAs($user)
        ->postJson('/ticket/luna/suggest', ['text' => 'Koneksi wifi lambat di lab'])
        ->assertStatus(200)
        ->assertJson([
            'success' => true,
            'suggestion' => [
                'category' => 'Jaringan & Internet',
                'service_item' => 'Wi-Fi Kampus (Eduroam / TelU-Guest)',
                'authority' => 'local',
            ],
        ]);

    // Interactive Chat
    $this->actingAs($user)
        ->postJson('/ticket/luna/chat', ['message' => 'Bagaimana cara connect ke eduroam?'])
        ->assertStatus(200)
        ->assertJsonStructure(['reply', 'timestamp']);
});

test('ticket assignment assigns present technician on duty today', function () {
    $technician = User::factory()->create(['type' => 'Staf', 'name' => 'Teknisi Siaga']);

    // Create attendance record for today using tanggal and jam_masuk
    Presence::create([
        'user_id' => $technician->id,
        'tanggal' => now()->format('Y-m-d'),
        'jam_masuk' => '08:00:00',
    ]);

    $assignmentService = app(TicketAssignmentService::class);
    $assigned = $assignmentService->assignTechnician();

    expect($assigned)->not->toBeNull();
    expect($assigned->id)->toBe($technician->id);
});
