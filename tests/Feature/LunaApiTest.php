<?php

use App\Enums\TicketStatus;
use App\Models\InventoryItem;
use App\Models\Ticket;
use App\Models\User;
use App\Services\LunaAiService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** @var TestCase $this */
beforeEach(function () {
    $this->testKbPath = storage_path('framework/testing/luna-kb-'.uniqid());

    config([
        'services.luna.api_key' => 'test-secret-luna-key',
        'services.luna.allowed_wa_number' => '081234567890',
        'services.luna.kb_path' => $this->testKbPath,
    ]);
});

afterEach(function () {
    if (isset($this->testKbPath) && File::isDirectory($this->testKbPath)) {
        File::deleteDirectory($this->testKbPath);
    }
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
    expect($ticket->user->email)->toBe('luna-whatsapp@simass.invalid');
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

test('luna api trusts dynamic ai category, service_item, and authority from payload', function () {
    User::factory()->create(['type' => 'Staf', 'phone' => '081234567890']);

    $res = $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', [
            'phone' => '081234567890',
            'description' => 'Permintaan pembukaan port firewall untuk server riset AI di Data Center.',
            'location' => 'Ruang Server PuTI Lt. 2',
            'category' => 'Keamanan & Firewall',
            'service_item' => 'Whitelist Port Server Riset',
            'authority' => 'local',
            'priority' => 'Tinggi',
        ]);

    $res->assertStatus(201)
        ->assertJsonPath('data.category', 'Keamanan & Firewall')
        ->assertJsonPath('data.service_item', 'Whitelist Port Server Riset')
        ->assertJsonPath('data.authority', 'local')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.scope', 'internal_surabaya');
});

test('luna api handles whatsapp input errors, privacy guardrail, unknown asset warning, and deduplication', function () {
    User::factory()->create(['type' => 'Staf', 'phone' => '081234567890']);

    // 1. Reject WhatsApp group JID (@g.us)
    config(['services.luna.allowed_wa_number' => '']);
    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', [
            'phone' => '12036304215523@g.us',
            'description' => 'Wi-Fi mati di ruang rapat utama.',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'luna_reply', 'errors']);

    // 2. Privacy guardrail blocks raw password in description
    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', [
            'phone' => '081234567890',
            'description' => 'Tolong reset akun saya, password saya Rahasia2026!',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.description.0', 'Terdeteksi informasi kredensial sensitif (password/PIN).');

    // 3. Unknown asset_code still creates ticket with warning & deduplicates webhook retry
    $payload = [
        'phone' => '081234567890',
        'description' => 'Switch lantai 2 gedung rektorat lampu indikatornya mati total sejak pukul 08.00.',
        'location' => 'Gedung Rektorat Lt. 2',
        'asset_code' => 'inv-tidak-ada-999',
    ];

    $first = $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', $payload)
        ->assertStatus(201)
        ->assertJsonPath('data.inventory_item', null);

    expect($first->json('warnings.0'))->toContain('INV-TIDAK-ADA-999');

    $ticketNumber = $first->json('data.ticket_number');

    // Immediate duplicate webhook retry returns 200 with duplicate: true
    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', $payload)
        ->assertStatus(200)
        ->assertJsonPath('duplicate', true)
        ->assertJsonPath('data.ticket_number', $ticketNumber);

    expect(Ticket::where('ticket_number', $ticketNumber)->count())->toBe(1);

    // 4. Lowercase ticket_number with '#' prefix works on GET show
    $this->withToken('test-secret-luna-key')
        ->getJson('/api/v1/luna/tickets/'.strtolower($ticketNumber))
        ->assertStatus(200)
        ->assertJsonPath('data.ticket_number', $ticketNumber);
});

test('luna classifies complaints by word, not by fragments of the payload tail', function (string $complaint, ?string $expected) {
    $tail = "\n\n---\nJenis laporan: INCIDENT\nStatus pelapor: Mahasiswa\nNo. WA pelapor: 6281234567890";

    expect(app(LunaAiService::class)->suggestService($complaint.$tail)['service_item'] ?? null)->toBe($expected);
})->with([
    ['proyektor kelas A301 mati total', 'Proyektor / Perangkat Ruang Kelas'],
    ['akun SSO terkunci', 'Akun iGracias & Single Sign-On (SSO)'],
    ['tolong mereset password saya', 'Akun iGracias & Single Sign-On (SSO)'],
    ['emailku tidak bisa menerima pesan', 'Email Kampus & Lisensi Microsoft 365'],
    ['LMS CeLOE tidak bisa dibuka', 'LMS CeLOE & Perkuliahan Daring'],
    ['access point lantai 2 mati', 'Wi-Fi Kampus (Eduroam / TelU-Guest)'],
    ['processor laptop panas', null],
]);

test('whatsapp reporters share one locked luna account and keep their own name', function () {
    config(['services.luna.allowed_wa_number' => '']);
    // Nomor HP dan NIM akun ini sama dengan pelapor: tetap tidak boleh jadi pemilik tiket.
    $staff = User::factory()->create(['type' => 'Staf', 'nim' => '1234567890', 'phone' => '081111111111']);

    $create = fn (string $phone, string $name): Ticket => Ticket::where(
        'ticket_number',
        $this->withToken('test-secret-luna-key')
            ->postJson('/api/v1/luna/tickets', [
                'phone' => $phone,
                'reporter_name' => $name,
                'nim' => '1234567890',
                'description' => 'Proyektor di kelas A301 tidak menyala sejak pagi.',
                'location' => 'Gedung A R.301',
            ])
            ->assertStatus(201)
            ->json('data.ticket_number')
    )->firstOrFail();

    $first = $create('081111111111', 'Pelapor Satu');
    $second = $create('082222222222', 'Pelapor Dua');
    $account = $first->user;

    expect($account->id)->not->toBe($staff->id);
    expect($second->user_id)->toBe($account->id);
    expect($account->is_active)->toBeFalse();
    expect($account->isTicketingStaff())->toBeFalse();

    foreach ([route('ticket.show', $first), route('ticket.tasks'), route('ticket.history')] as $url) {
        $this->actingAs($staff)->get($url)
            ->assertOk()
            ->assertSee('Pelapor Satu')
            ->assertDontSee($account->name);
    }

    // Akun yang dihapus admin tidak boleh membuat halaman tiketnya error atau menolak laporan baru.
    $account->delete();
    $this->get(route('ticket.show', $first))->assertOk()->assertSee('Pelapor Satu');

    // Email akun terhapus dilepas, jadi laporan berikutnya memakai akun terkunci yang baru.
    $replacement = $create('083333333333', 'Pelapor Tiga')->user;
    expect($replacement->id)->not->toBe($account->id);
    expect($replacement->email)->toBe('luna-whatsapp@simass.invalid');
    expect($replacement->is_active)->toBeFalse();
});

test('privacy guardrail blocks secret values but accepts complaints about passwords', function (string $description, int $status) {
    config(['services.luna.allowed_wa_number' => '']);

    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', [
            'phone' => '081234567890',
            'description' => $description,
            'location' => 'Gedung A R.301',
        ])
        ->assertStatus($status);
})->with([
    ['lupa password igracias', 201],
    ['password email tidak bisa dipakai', 201],
    ['tidak bisa login, password ditolak terus', 201],
    ['password saya salah! tolong dibantu', 201],
    ['lupa password office365', 201],
    ['Kendala password: lupa sejak kemarin', 201],
    ['kata sandi akun microsoft kadaluarsa', 201],
    ['password saya: Rahasia123', 422],
    ['password: rahasia', 422],
    ['passwordnya adalah kucingku', 422],
    ['kata sandi saya Rahasia2026!', 422],
    ['pin saya 123456', 422],
]);

test('luna ticket list supports incremental sync across timezones', function () {
    $user = User::factory()->create();
    $make = fn (array $attributes) => Ticket::factory()->create($attributes + [
        'user_id' => $user->id,
        'source_channel' => 'whatsapp_luna',
    ]);

    $stale = $make(['updated_at' => now()->subHours(3)]);
    $web = $make(['source_channel' => 'web']);
    $newest = $make(['updated_at' => now()->subMinute()]);
    $oldest = $make(['updated_at' => now()->subMinutes(30)]);
    foreach (range(1, 22) as $i) {
        $make(['updated_at' => now()->subMinutes(10)]);
    }

    // Nomor bot di header bukan penyaring tiket.
    $response = $this->withToken('test-secret-luna-key')
        ->withHeader('X-Wa-Number', '081234567890')
        ->getJson('/api/v1/luna/tickets?'.http_build_query([
            'updated_since' => now()->subHour()->utc()->toIso8601String(),
        ]))
        ->assertOk()
        ->assertJsonStructure(['data' => [['ticket_number', 'updated_at']]]);

    $numbers = collect($response->json('data'))->pluck('ticket_number');
    expect($numbers)->toHaveCount(24);
    expect($numbers->first())->toBe($oldest->ticket_number);
    expect($numbers->last())->toBe($newest->ticket_number);
    expect($numbers->all())->not->toContain($stale->ticket_number);
    expect($numbers->all())->not->toContain($web->ticket_number);

    $this->getJson('/api/v1/luna/tickets?updated_since=bukan-tanggal')->assertStatus(422);
});

test('luna knowledge base api supports listing, creating, appending, syncing, deleting, and blocks invalid filenames', function () {
    // 1. Create LUNA_03_KategoriLayanan.md via POST
    $createRes = $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/knowledge-base', [
            'document' => 'LUNA_03_KategoriLayanan',
            'section_title' => 'Jaringan & Internet',
            'content' => "- Wi-Fi Kampus (local)\n- Kabel LAN (local)",
        ]);

    $createRes->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.filename', 'LUNA_03_KategoriLayanan.md')
        ->assertJsonPath('data.is_standard', true);

    expect($createRes->json('data.content'))->toContain('## Jaringan & Internet');

    // 2. Append new section to LUNA_03_KategoriLayanan.md
    $appendRes = $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/knowledge-base', [
            'document' => 'LUNA_03_KategoriLayanan.md',
            'section_title' => 'Keamanan & Firewall',
            'content' => '- Whitelist Port Server Riset (local)',
            'mode' => 'append',
        ]);

    $appendRes->assertStatus(200);
    expect($appendRes->json('data.content'))
        ->toContain('## Jaringan & Internet')
        ->toContain('## Keamanan & Firewall');

    // 3. PUT full upsert for LUNA_01_Identitas.md
    $this->withToken('test-secret-luna-key')
        ->putJson('/api/v1/luna/knowledge-base/LUNA_01_Identitas.md', [
            'content' => "# Identitas Luna AI\nSaya adalah asisten virtual Helpdesk PuTI Surabaya.",
        ])
        ->assertStatus(200)
        ->assertJsonPath('data.filename', 'LUNA_01_Identitas.md');

    // 4. GET index with include_content & include_sync_data
    $indexRes = $this->withToken('test-secret-luna-key')
        ->getJson('/api/v1/luna/knowledge-base?include_content=1&include_sync_data=1')
        ->assertStatus(200)
        ->assertJsonPath('success', true)
        ->assertJsonPath('count', 2)
        ->assertJsonStructure([
            'standard_documents',
            'data' => [['filename', 'key', 'sha256', 'content', 'updated_at']],
            'sync_data' => ['ticket_services', 'service_catalogs', 'inventory_categories'],
        ]);

    // 5. GET single document
    $this->withToken('test-secret-luna-key')
        ->getJson('/api/v1/luna/knowledge-base/LUNA_01_Identitas')
        ->assertStatus(200)
        ->assertJsonPath('data.key', 'LUNA_01_Identitas');

    // 6. Reject invalid / path-traversal filenames
    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/knowledge-base', [
            'document' => '../.env',
            'content' => 'malicious content',
        ])
        ->assertStatus(422)
        ->assertJsonPath('success', false);

    // 7. DELETE document
    $this->withToken('test-secret-luna-key')
        ->deleteJson('/api/v1/luna/knowledge-base/LUNA_01_Identitas.md')
        ->assertStatus(200)
        ->assertJsonPath('success', true);

    $this->withToken('test-secret-luna-key')
        ->getJson('/api/v1/luna/knowledge-base/LUNA_01_Identitas.md')
        ->assertStatus(404);
});
