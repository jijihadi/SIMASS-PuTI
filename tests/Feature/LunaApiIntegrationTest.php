<?php

use App\Models\Ticket;
use App\Models\User;
use Tests\TestCase;

/** @var TestCase $this */
beforeEach(function () {
    config([
        'services.luna.api_key' => 'test-secret-luna-key',
        'services.luna.allowed_wa_number' => '081234567890',
    ]);

    User::factory()->create(['type' => 'Staf', 'phone' => '6281234567890']);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lunaPayload(array $overrides = []): array
{
    return array_merge([
        'phone' => '6281234567890',
        'reporter_phone' => '081299998888',
        'description' => 'Wifi di ruang 301 Gedung A tidak bisa connect sejak pagi.',
        'location' => 'Gedung A R.301',
    ], $overrides);
}

test('reporter_phone is normalized, stored, and returned without being checked against the allowed number', function () {
    $response = $this->withToken('test-secret-luna-key')
        ->withHeader('X-Wa-Number', '6281234567890')
        ->postJson('/api/v1/luna/tickets', lunaPayload());

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.reporter_phone', '6281299998888');

    expect(Ticket::first()->reporter_phone)->toBe('6281299998888');
});

test('reporter_phone in jid format is normalized', function () {
    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload(['reporter_phone' => '+62 812-9999-8888@s.whatsapp.net']))
        ->assertCreated()
        ->assertJsonPath('data.reporter_phone', '6281299998888');
});

test('show returns the same shape as store including reporter_phone', function () {
    $created = $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload())
        ->assertCreated();

    $ticketNumber = $created->json('data.ticket_number');

    $shown = $this->withToken('test-secret-luna-key')
        ->withHeader('X-Wa-Number', '6281234567890')
        ->getJson("/api/v1/luna/tickets/{$ticketNumber}")
        ->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'ticket_number', 'description', 'category', 'service_item', 'location',
                'status' => ['value', 'label', 'needs_clarification', 'is_final'],
                'assignee', 'inventory_item',
                'sla_due_at', 'sla_paused_at', 'resolved_at', 'created_at',
                'reporter_phone',
            ],
        ])
        ->assertJsonPath('data.reporter_phone', '6281299998888');

    $expectedKeys = collect($created->json('data'))->except('messages')->keys()->sort()->values()->all();
    $actualKeys = collect($shown->json('data'))->except('messages')->keys()->sort()->values()->all();
    expect($actualKeys)->toBe($expectedKeys);

    expect($shown->json('data.created_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/');
});

test('ticket list is filtered by reporter_phone, not by the bot number', function () {
    $create = fn (string $reporterPhone): string => $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload(['reporter_phone' => $reporterPhone]))
        ->assertCreated()
        ->json('data.ticket_number');

    $mine = $create('081299998888');
    $create('081277776666');

    $this->withHeader('X-Wa-Number', '6281234567890')
        ->getJson('/api/v1/luna/tickets?reporter_phone=081299998888')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ticket_number', $mine);

    $this->getJson('/api/v1/luna/tickets')->assertOk()->assertJsonCount(2, 'data');
});

test('invalid reporter_phone and oversized idempotency key return 422', function () {
    foreach (['12036304215523@g.us', '12345'] as $reporterPhone) {
        $this->withToken('test-secret-luna-key')
            ->postJson('/api/v1/luna/tickets', lunaPayload(['reporter_phone' => $reporterPhone]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reporter_phone');
    }

    $this->withHeader('Idempotency-Key', str_repeat('x', 101))
        ->postJson('/api/v1/luna/tickets', lunaPayload())
        ->assertUnprocessable();

    expect(Ticket::count())->toBe(0);
});

test('short complaints carrying the luna metadata block are not paused for clarification', function () {
    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload([
            'description' => "Proyektor mati\n\n---\nJenis laporan: PENGADUAN IT\nNo. WA pelapor: 6281299998888",
        ]))
        ->assertCreated()
        ->assertJsonPath('data.title', 'Proyektor mati')
        ->assertJsonPath('data.status.needs_clarification', false);
});

test('ticket number follows TIC-YYYYMM-#### format', function () {
    $ticketNumber = $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload())
        ->assertCreated()
        ->json('data.ticket_number');

    expect($ticketNumber)->toMatch('/^TIC-\d{4}(0[1-9]|1[0-2])-\d{4,}$/');
});

test('store without category and service_item responds well under 3 seconds', function () {
    $durations = [];

    foreach (range(1, 10) as $i) {
        $start = microtime(true);

        $this->withToken('test-secret-luna-key')
            ->postJson('/api/v1/luna/tickets', lunaPayload(['description' => "Wifi lantai {$i} Gedung A tidak bisa connect sama sekali."]))
            ->assertCreated();

        $durations[] = microtime(true) - $start;
    }

    sort($durations);
    $p95 = $durations[(int) ceil(0.95 * count($durations)) - 1];

    expect($p95)->toBeLessThan(3.0);
});

test('unknown ticket returns 404 json', function () {
    $this->withToken('test-secret-luna-key')
        ->getJson('/api/v1/luna/tickets/TIC-209901-9999')
        ->assertNotFound()
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message']);
});

test('invalid key returns 401 json and wrong phone returns 403 json', function () {
    $this->withToken('wrong-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload())
        ->assertUnauthorized()
        ->assertJsonPath('success', false);

    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload(['phone' => '089999999999']))
        ->assertForbidden()
        ->assertJsonPath('success', false);
});

test('validation errors return 422 json with success false', function () {
    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', ['phone' => '6281234567890'])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors('description');
});

test('luna routes never return html even without json accept header', function () {
    $response = $this->withToken('test-secret-luna-key')
        ->get('/api/v1/luna/tickets/TIC-209901-9999');

    $response->assertNotFound();
    expect($response->headers->get('Content-Type'))->toContain('application/json');

    $methodNotAllowed = $this->withToken('test-secret-luna-key')
        ->delete('/api/v1/luna/tickets');

    $methodNotAllowed->assertStatus(405)->assertJsonPath('success', false);
    expect($methodNotAllowed->headers->get('Content-Type'))->toContain('application/json');
});

test('auto title ignores the metadata block after the separator', function () {
    $description = "Wifi di ruang 301 tidak bisa connect\n\n---\nJenis laporan: PENGADUAN IT\nStatus pelapor: mahasiswa\nKategori (Luna): Jaringan\nNo. WA pelapor: 6281299998888";

    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload(['description' => $description]))
        ->assertCreated()
        ->assertJsonPath('data.title', 'Wifi di ruang 301 tidak bisa connect')
        ->assertJsonPath('data.description', $description);
});

test('official reporter metadata fields are stored and returned', function () {
    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload([
            'reporter_status' => 'mahasiswa',
            'unit_kerja' => 'Fakultas Informatika',
            'request_type' => 'PENGADUAN IT',
        ]))
        ->assertCreated()
        ->assertJsonPath('data.reporter_status', 'mahasiswa')
        ->assertJsonPath('data.unit_kerja', 'Fakultas Informatika')
        ->assertJsonPath('data.request_type', 'PENGADUAN IT');
});

test('same idempotency key returns the existing ticket instead of creating a new one', function () {
    $first = $this->withToken('test-secret-luna-key')
        ->withHeader('Idempotency-Key', 'luna-msg-123')
        ->postJson('/api/v1/luna/tickets', lunaPayload())
        ->assertCreated();

    $second = $this->withToken('test-secret-luna-key')
        ->withHeader('Idempotency-Key', 'luna-msg-123')
        // Deskripsi dibedakan supaya yang diuji kuncinya, bukan deduplikasi 60 detik.
        ->postJson('/api/v1/luna/tickets', lunaPayload(['description' => 'Wifi di ruang 302 Gedung A tidak bisa connect.']))
        ->assertOk()
        ->assertJsonPath('duplicate', true);

    expect($second->json('data.ticket_number'))->toBe($first->json('data.ticket_number'));
    expect(Ticket::count())->toBe(1);

    $this->flushHeaders()
        ->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', lunaPayload([
            'external_id' => 'luna-msg-123',
            'description' => 'Wifi di ruang 303 Gedung A tidak bisa connect.',
        ]))
        ->assertOk()
        ->assertJsonPath('data.ticket_number', $first->json('data.ticket_number'));
});

test('idempotency key older than 24 hours creates a new ticket', function () {
    $this->withToken('test-secret-luna-key')
        ->withHeader('Idempotency-Key', 'luna-msg-old')
        ->postJson('/api/v1/luna/tickets', lunaPayload())
        ->assertCreated();

    $this->travel(25)->hours();

    $this->withToken('test-secret-luna-key')
        ->withHeader('Idempotency-Key', 'luna-msg-old')
        ->postJson('/api/v1/luna/tickets', lunaPayload())
        ->assertCreated();

    expect(Ticket::count())->toBe(2);
});
