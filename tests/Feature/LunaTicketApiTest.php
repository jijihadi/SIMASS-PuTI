<?php

use App\Enums\TicketStatus;
use App\Models\InventoryItem;
use App\Models\Ticket;
use App\Models\User;
use Tests\TestCase;

/** @var TestCase $this */
beforeEach(function () {
    config([
        'services.luna.api_key' => 'test-secret-luna-key',
        'services.luna.allowed_wa_number' => '081234567890',
    ]);
});

test('luna whatsapp api rejects missing or invalid api key', function () {
    $this->postJson('/api/v1/luna/tickets', [
        'phone' => '081234567890',
        'description' => 'Wi-Fi ruang rapat mati total.',
    ])->assertStatus(401);
});

test('luna whatsapp api rejects non-whitelisted whatsapp phone numbers', function () {
    User::factory()->create(['type' => 'Staf']);

    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', [
            'phone' => '089999999999',
            'description' => 'Wi-Fi di Gedung A Lantai 2 R.203 putus-putus sejak pagi.',
            'location' => 'Gedung A Lt. 2 R.203',
        ])
        ->assertStatus(403)
        ->assertJson([
            'success' => false,
        ]);
});

test('whitelisted whatsapp number can create ticket via luna api with auto service inference and inventory sync', function () {
    $staff = User::factory()->create([
        'type' => 'Staf',
        'phone' => '081234567890',
        'name' => 'Admin WhatsApp Luna',
    ]);

    $asset = InventoryItem::factory()->create([
        'asset_code' => 'INV-PUTI-2026-0099',
        'name' => 'Proyektor Ruang Kelas A301',
        'location_detail' => 'Gedung A R.301',
    ]);

    $response = $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', [
            'phone' => '+62 812-3456-7890@s.whatsapp.net',
            'description' => 'Proyektor di kelas A301 tidak menampilkan gambar HDMI sama sekali ketika dicolok ke laptop.',
            'asset_code' => 'INV-PUTI-2026-0099',
            'priority' => 'high',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status.value', TicketStatus::Open->value)
        ->assertJsonPath('data.source_channel', 'whatsapp_luna')
        ->assertJsonPath('data.inventory_item.asset_code', 'INV-PUTI-2026-0099');

    $ticketNumber = $response->json('data.ticket_number');
    $ticket = Ticket::where('ticket_number', $ticketNumber)->first();
    expect($ticket)->not->toBeNull();
    expect($ticket->user_id)->toBe($staff->id);
    expect($ticket->reporter_phone)->toBe('6281234567890');
    expect($ticket->inventory_item_id)->toBe($asset->id);
});

test('luna whatsapp api supports status check, clarification reply, and self-cancel', function () {
    User::factory()->create([
        'type' => 'Staf',
        'phone' => '6281234567890',
    ]);

    // 1. Ambiguous message creates open ticket with needs_clarification
    $createRes = $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', [
            'phone' => '081234567890',
            'description' => 'wifi error',
        ])
        ->assertStatus(201);

    $ticketNumber = $createRes->json('data.ticket_number');
    expect($createRes->json('data.status.value'))->toBe(TicketStatus::Open->value);
    expect($createRes->json('data.status.needs_clarification'))->toBeTrue();

    // 2. Reply via WhatsApp resumes SLA & clears needs_clarification
    $this->withToken('test-secret-luna-key')
        ->postJson("/api/v1/luna/tickets/{$ticketNumber}/reply", [
            'phone' => '081234567890',
            'message' => 'Lokasi di Gedung A Lantai 3 R.305, tidak bisa dapat IP address.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status.needs_clarification', false)
        ->assertJsonPath('data.scope', 'internal_surabaya');

    // 3. Check status via GET endpoint
    $this->withToken('test-secret-luna-key')
        ->getJson("/api/v1/luna/tickets/{$ticketNumber}")
        ->assertStatus(200)
        ->assertJsonPath('data.ticket_number', $ticketNumber);

    // 4. Cancel while open via WhatsApp
    $this->withToken('test-secret-luna-key')
        ->postJson("/api/v1/luna/tickets/{$ticketNumber}/cancel", [
            'phone' => '081234567890',
            'status_note' => 'Sudah sembuh sendiri setelah reconnect.',
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.status.value', TicketStatus::Canceled->value);
});
