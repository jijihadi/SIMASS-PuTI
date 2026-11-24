<?php

use App\Enums\TicketStatus;
use App\Events\AuditLogRecorded;
use App\Events\InventoryItemUpdated;
use App\Events\PresenceRecorded;
use App\Events\TicketCreated;
use App\Events\TicketMessagePosted;
use App\Events\TicketStatusUpdated;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Presence;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** @var TestCase $this */
beforeEach(function (): void {
    $this->testKbPath = storage_path('framework/testing/luna-kb-realtime-'.uniqid());
});

afterEach(function (): void {
    if (isset($this->testKbPath) && File::isDirectory($this->testKbPath)) {
        File::deleteDirectory($this->testKbPath);
    }
});

/**
 * @param  list<PrivateChannel>  $channels
 * @return list<string>
 */
function channelNames(array $channels): array
{
    return array_map(
        static fn (PrivateChannel $channel): string => $channel->name,
        $channels,
    );
}

function enableReverbForChannelAuthTests(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-reverb-key',
        'broadcasting.connections.reverb.secret' => 'test-reverb-secret',
        'broadcasting.connections.reverb.app_id' => 'test-reverb-app',
        'broadcasting.connections.reverb.options' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'scheme' => 'http',
            'useTLS' => false,
        ],
    ]);

    Broadcast::forgetDrivers();
    require base_path('routes/channels.php');
}

test('creating ticket via web dispatches TicketCreated event with trimmed payload', function (): void {
    Event::fake([TicketCreated::class]);

    $reporter = User::factory()->create(['is_active' => true]);

    $this->actingAs($reporter)->post('/ticket', [
        'category' => 'Jaringan & Internet',
        'service_item' => 'Wi-Fi Kampus (Eduroam / TelU-Guest)',
        'title' => 'Wi-Fi Gedung Kuliah Umum Terputus',
        'description' => 'SSID eduroam tidak dapat terhubung sejak pukul 09.00 di ruang kelas lantai 3.',
        'location' => 'Gedung Kuliah Umum Lt. 3',
        'priority' => 'high',
    ])->assertRedirect();

    Event::assertDispatched(TicketCreated::class, function (TicketCreated $event): bool {
        $payload = $event->broadcastWith();
        $channels = channelNames($event->broadcastOn());

        return $event->broadcastAs() === 'ticket.created'
            && in_array('private-noc.tickets', $channels, true)
            && $payload['title'] === 'Wi-Fi Gedung Kuliah Umum Terputus'
            && $payload['source_channel'] === 'web'
            && ! array_key_exists('password', $payload);
    });
});

test('creating ticket via whatsapp luna api dispatches TicketCreated event', function (): void {
    Event::fake([TicketCreated::class]);

    config([
        'services.luna.api_key' => 'test-secret-luna-key',
        'services.luna.allowed_wa_number' => '081234567890',
        'services.luna.kb_path' => $this->testKbPath,
    ]);

    User::factory()->create([
        'type' => 'Staf',
        'phone' => '081234567890',
        'is_active' => true,
    ]);

    $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', [
            'phone' => '081234567890',
            'description' => 'Jaringan LAN ruang lab komputer gedung B mati total sejak pagi.',
            'location' => 'Gedung B Lab 1',
            'priority' => 'high',
        ])
        ->assertStatus(201);

    Event::assertDispatched(TicketCreated::class, function (TicketCreated $event): bool {
        $payload = $event->broadcastWith();

        return $payload['source_channel'] === 'whatsapp_luna'
            && in_array('private-noc.tickets', channelNames($event->broadcastOn()), true);
    });
});

test('internal staff notes broadcast exclusively to private internal ticket channel', function (): void {
    Event::fake([TicketMessagePosted::class]);

    $reporter = User::factory()->create(['is_active' => true, 'type' => 'Mahasiswa']);
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);
    $ticket = Ticket::factory()->open()->create([
        'user_id' => $reporter->id,
        'assigned_to' => $staff->id,
    ]);

    $this->actingAs($staff)->post("/ticket/{$ticket->id}/reply", [
        'message' => 'Investigasi internal: cek port uplink switch rack lantai 2.',
        'is_internal' => '1',
    ])->assertRedirect();

    Event::assertDispatched(TicketMessagePosted::class, function (TicketMessagePosted $event) use ($ticket, $reporter, $staff): bool {
        $channels = channelNames($event->broadcastOn());

        return $event->message->is_internal === true
            && $channels === ["private-ticket.{$ticket->id}.internal"]
            && ! in_array("private-ticket.{$ticket->id}", $channels, true)
            && ! in_array("private-user.{$reporter->id}", $channels, true)
            && ! in_array("private-user.{$staff->id}", $channels, true);
    });
});

test('public replies broadcast to public ticket channel and target recipient user channel', function (): void {
    Event::fake([TicketMessagePosted::class]);

    $reporter = User::factory()->create(['is_active' => true, 'type' => 'Mahasiswa']);
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);
    $ticket = Ticket::factory()->inProgress()->create([
        'user_id' => $reporter->id,
        'assigned_to' => $staff->id,
    ]);

    $this->actingAs($staff)->post("/ticket/{$ticket->id}/reply", [
        'message' => 'Access point sudah kami restart, mohon dicoba kembali.',
    ])->assertRedirect();

    Event::assertDispatched(TicketMessagePosted::class, function (TicketMessagePosted $event) use ($ticket, $reporter): bool {
        if ($event->message->sender_type !== 'staff') {
            return false;
        }

        $channels = channelNames($event->broadcastOn());

        return in_array("private-ticket.{$ticket->id}", $channels, true)
            && in_array("private-user.{$reporter->id}", $channels, true)
            && ! in_array("private-ticket.{$ticket->id}.internal", $channels, true);
    });

    Event::fake([TicketMessagePosted::class]);

    $this->actingAs($reporter)->post("/ticket/{$ticket->id}/reply", [
        'message' => 'Terima kasih, saat ini Wi-Fi sudah kembali normal.',
    ])->assertRedirect();

    Event::assertDispatched(TicketMessagePosted::class, function (TicketMessagePosted $event) use ($ticket, $staff): bool {
        if ($event->message->sender_type !== 'user') {
            return false;
        }

        $channels = channelNames($event->broadcastOn());

        return in_array("private-ticket.{$ticket->id}", $channels, true)
            && in_array("private-user.{$staff->id}", $channels, true)
            && ! in_array("private-ticket.{$ticket->id}.internal", $channels, true);
    });
});

test('updating ticket status dispatches TicketStatusUpdated event to noc, ticket, and reporter channels', function (): void {
    Event::fake([TicketStatusUpdated::class]);

    $reporter = User::factory()->create(['is_active' => true]);
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);
    $ticket = Ticket::factory()->open()->create([
        'user_id' => $reporter->id,
        'assigned_to' => null,
    ]);

    $this->actingAs($staff)->patch("/ticket/{$ticket->id}/status", [
        'status' => TicketStatus::InProgress->value,
    ])->assertSessionHasNoErrors();

    Event::assertDispatched(TicketStatusUpdated::class, function (TicketStatusUpdated $event) use ($ticket, $reporter): bool {
        $channels = channelNames($event->broadcastOn());
        $payload = $event->broadcastWith();

        return $event->broadcastAs() === 'ticket.status.updated'
            && $payload['status']['value'] === TicketStatus::InProgress->value
            && in_array('private-noc.tickets', $channels, true)
            && in_array("private-ticket.{$ticket->id}", $channels, true)
            && in_array("private-user.{$reporter->id}", $channels, true);
    });
});

test('broadcast channel authorization enforces ownership, staff roles, active status, and safe parameter checks', function (): void {
    $reporter = User::factory()->create(['is_active' => true, 'type' => 'Mahasiswa']);
    $otherReporter = User::factory()->create(['is_active' => true, 'type' => 'Mahasiswa']);
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);
    $inactiveStaff = User::factory()->create(['is_active' => false, 'type' => 'Staf']);

    $unitHeadRole = Role::firstOrCreate(['name' => 'unit-head']);
    $unitHead = User::factory()->create(['is_active' => true, 'type' => 'Dosen']);
    $unitHead->assignRole($unitHeadRole);

    $ownTicket = Ticket::factory()->open()->create(['user_id' => $reporter->id]);
    $otherTicket = Ticket::factory()->open()->create(['user_id' => $otherReporter->id]);

    enableReverbForChannelAuthTests();

    $socketId = '1234.5678';

    // Reporter: allowed own ticket + own personal channel; denied others, internal notes, and NOC
    $this->actingAs($reporter)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => "private-ticket.{$ownTicket->id}",
    ])->assertOk();

    $this->actingAs($reporter)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => "private-user.{$reporter->id}",
    ])->assertOk();

    $this->actingAs($reporter)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => "private-ticket.{$otherTicket->id}",
    ])->assertForbidden();

    $this->actingAs($reporter)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => "private-ticket.{$ownTicket->id}.internal",
    ])->assertForbidden();

    $this->actingAs($reporter)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => 'private-noc.tickets',
    ])->assertForbidden();

    $this->actingAs($reporter)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => "private-user.{$otherReporter->id}",
    ])->assertForbidden();

    // Inactive staff: denied everywhere
    foreach ([
        'private-noc.tickets',
        "private-ticket.{$ownTicket->id}",
        "private-ticket.{$ownTicket->id}.internal",
        "private-user.{$inactiveStaff->id}",
    ] as $channel) {
        $this->actingAs($inactiveStaff)->post('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => $channel,
        ])->assertForbidden();
    }

    // Active staff & unit-head: allowed NOC, any public/internal ticket channel, and own user channel
    foreach ([$staff, $unitHead] as $privilegedUser) {
        $this->actingAs($privilegedUser)->post('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => 'private-noc.tickets',
        ])->assertOk();

        $this->actingAs($privilegedUser)->post('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => "private-ticket.{$ownTicket->id}",
        ])->assertOk();

        $this->actingAs($privilegedUser)->post('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => "private-ticket.{$ownTicket->id}.internal",
        ])->assertOk();

        $this->actingAs($privilegedUser)->post('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => "private-user.{$privilegedUser->id}",
        ])->assertOk();
    }

    // Malformed non-numeric channel IDs must return 403 rather than 500 TypeError
    $this->actingAs($staff)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => 'private-ticket.not-an-id',
    ])->assertForbidden();

    $this->actingAs($staff)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => 'private-user.not-an-id',
    ])->assertForbidden();
});

test('presence observer dispatches PresenceRecorded on check-in, check-out, and delete', function (): void {
    Event::fake([PresenceRecorded::class]);

    $staff = User::factory()->create(['is_active' => true, 'name' => 'Budi Presensi']);

    $presence = Presence::create([
        'user_id' => $staff->id,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => '08:00:00',
        'hari' => 'Senin',
    ]);

    Event::assertDispatched(PresenceRecorded::class, function (PresenceRecorded $event) use ($staff): bool {
        $payload = $event->broadcastWith();

        return $event->broadcastAs() === 'presence.recorded'
            && channelNames($event->broadcastOn()) === ['private-presence.live']
            && $payload['action'] === 'check_in'
            && $payload['user_name'] === $staff->name;
    });

    Event::fake([PresenceRecorded::class]);

    $presence->update(['jam_pulang' => '17:00:00']);

    Event::assertDispatched(PresenceRecorded::class, function (PresenceRecorded $event): bool {
        return $event->broadcastWith()['action'] === 'check_out';
    });

    Event::fake([PresenceRecorded::class]);

    $presence->delete();

    Event::assertDispatched(PresenceRecorded::class, function (PresenceRecorded $event): bool {
        return $event->broadcastWith()['action'] === 'deleted';
    });
});

test('inventory observer dispatches InventoryItemUpdated on create, update, and delete', function (): void {
    Event::fake([InventoryItemUpdated::class]);

    $item = InventoryItem::factory()->create([
        'asset_code' => 'INV-PUTI-2026-7777',
        'name' => 'Switch Core Rack A',
        'condition' => 'good',
        'status' => 'active',
    ]);

    Event::assertDispatched(InventoryItemUpdated::class, function (InventoryItemUpdated $event): bool {
        $payload = $event->broadcastWith();

        return $event->broadcastAs() === 'inventory.item.updated'
            && channelNames($event->broadcastOn()) === ['private-inventory.live']
            && $payload['action'] === 'created'
            && $payload['asset_code'] === 'INV-PUTI-2026-7777'
            && $payload['item_url'] !== null;
    });

    Event::fake([InventoryItemUpdated::class]);

    $item->update(['condition' => 'minor_damage', 'status' => 'under_repair']);

    Event::assertDispatched(InventoryItemUpdated::class, function (InventoryItemUpdated $event): bool {
        $payload = $event->broadcastWith();

        return $payload['action'] === 'updated'
            && $payload['condition'] === 'minor_damage'
            && $payload['status'] === 'under_repair';
    });

    Event::fake([InventoryItemUpdated::class]);

    $item->delete();

    Event::assertDispatched(InventoryItemUpdated::class, function (InventoryItemUpdated $event): bool {
        $payload = $event->broadcastWith();

        return $payload['action'] === 'deleted'
            && $payload['item_url'] === null;
    });
});

test('presence.live and inventory.live channels enforce active status and RBAC permissions', function (): void {
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);
    $inactiveStaff = User::factory()->create(['is_active' => false, 'type' => 'Staf']);

    $basicRole = Role::firstOrCreate(['name' => 'mahasiswa']);
    $regularUser = User::factory()->create(['is_active' => true, 'type' => 'Mahasiswa']);
    $regularUser->assignRole($basicRole);

    enableReverbForChannelAuthTests();

    $socketId = '1234.5678';

    // Active staff allowed on both channels
    $this->actingAs($staff)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => 'private-presence.live',
    ])->assertOk();

    $this->actingAs($staff)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => 'private-inventory.live',
    ])->assertOk();

    // Inactive staff & unprivileged regular user denied on both channels
    foreach ([$inactiveStaff, $regularUser] as $deniedUser) {
        $this->actingAs($deniedUser)->post('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => 'private-presence.live',
        ])->assertForbidden();

        $this->actingAs($deniedUser)->post('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => 'private-inventory.live',
        ])->assertForbidden();
    }
});

test('ticket presence channel authorizes owner and active staff with trimmed member info and denies others', function (): void {
    $owner = User::factory()->create(['is_active' => true, 'type' => 'Mahasiswa', 'name' => 'Ani Pelapor']);
    $otherUser = User::factory()->create(['is_active' => true, 'type' => 'Mahasiswa']);
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf', 'name' => 'Rudi Teknisi']);
    $inactiveStaff = User::factory()->create(['is_active' => false, 'type' => 'Staf']);

    $ticket = Ticket::factory()->open()->create(['user_id' => $owner->id]);

    enableReverbForChannelAuthTests();

    $socketId = '1234.5678';
    $presenceChannel = "presence-ticket.{$ticket->id}.presence";

    $ownerRes = $this->actingAs($owner)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => $presenceChannel,
    ])->assertOk();

    $ownerChannelData = json_decode((string) $ownerRes->json('channel_data'), true, 512, JSON_THROW_ON_ERROR);
    expect($ownerChannelData['user_info'])->toMatchArray([
        'id' => $owner->id,
        'name' => 'Ani Pelapor',
        'is_staff' => false,
    ]);
    expect($ownerChannelData['user_info'])->not->toHaveKey('email');

    $staffRes = $this->actingAs($staff)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => $presenceChannel,
    ])->assertOk();

    $staffChannelData = json_decode((string) $staffRes->json('channel_data'), true, 512, JSON_THROW_ON_ERROR);
    expect($staffChannelData['user_info'])->toMatchArray([
        'id' => $staff->id,
        'name' => 'Rudi Teknisi',
        'is_staff' => true,
    ]);

    foreach ([$otherUser, $inactiveStaff] as $denied) {
        $this->actingAs($denied)->post('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => $presenceChannel,
        ])->assertForbidden();
    }

    $this->actingAs($staff)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => 'presence-ticket.invalid.presence',
    ])->assertForbidden();
});

test('audit log observer dispatches AuditLogRecorded and restricts admin.audit channel to active super-admin', function (): void {
    Event::fake([AuditLogRecorded::class]);

    $superAdminRole = Role::firstOrCreate(['name' => 'super-admin']);
    $superAdmin = User::factory()->create(['is_active' => true, 'name' => 'Super Admin']);
    $superAdmin->assignRole($superAdminRole);

    $inactiveAdmin = User::factory()->create(['is_active' => false]);
    $inactiveAdmin->assignRole($superAdminRole);

    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);

    AuditLog::create([
        'user_id' => $superAdmin->id,
        'action' => 'user.updated',
        'model_type' => User::class,
        'model_id' => $staff->id,
        'old_values' => ['email' => 'old@example.com'],
        'new_values' => ['email' => 'new@example.com'],
        'ip_address' => '127.0.0.1',
        'created_at' => now(),
    ]);

    Event::assertDispatched(AuditLogRecorded::class, function (AuditLogRecorded $event): bool {
        $payload = $event->broadcastWith();

        return $event->broadcastAs() === 'audit.log.recorded'
            && channelNames($event->broadcastOn()) === ['private-admin.audit']
            && $payload['action'] === 'user.updated'
            && ! array_key_exists('old_values', $payload)
            && ! array_key_exists('new_values', $payload);
    });

    enableReverbForChannelAuthTests();

    $socketId = '1234.5678';

    $this->actingAs($superAdmin)->post('/broadcasting/auth', [
        'socket_id' => $socketId,
        'channel_name' => 'private-admin.audit',
    ])->assertOk();

    foreach ([$inactiveAdmin, $staff] as $denied) {
        $this->actingAs($denied)->post('/broadcasting/auth', [
            'socket_id' => $socketId,
            'channel_name' => 'private-admin.audit',
        ])->assertForbidden();
    }
});

test('realtime:health artisan command outputs valid json health report', function (): void {
    $this->artisan('realtime:health', ['--json' => true, '--no-connect' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('"broadcast_driver"');
});
