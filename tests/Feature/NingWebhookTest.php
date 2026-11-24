<?php

use App\Enums\TicketStatus;
use App\Events\NetworkAlertTriggered;
use App\Events\TicketCreated;
use App\Events\TicketStatusUpdated;
use App\Models\InventoryItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/** @var TestCase $this */
beforeEach(function (): void {
    config([
        'services.ning.webhook_secret' => 'test-ning-webhook-secret',
    ]);
});

test('ning webhook rejects missing or invalid credentials and signatures', function (): void {
    $payload = [
        'event_type' => 'OUTAGE_DETECTED',
        'device_name' => 'SW-CORE-GDA',
    ];

    $this->postJson('/api/v1/ning/webhook', $payload)->assertStatus(401);

    $this->withToken('wrong-secret')
        ->postJson('/api/v1/ning/webhook', $payload)
        ->assertStatus(401);

    $this->withHeader('X-Ning-Signature', 'sha256=deadbeef')
        ->postJson('/api/v1/ning/webhook', $payload)
        ->assertStatus(401);
});

test('ning webhook accepts valid hmac signature, creates linked ticket, deduplicates, and auto-resolves on restore', function (): void {
    Event::fake([TicketCreated::class, TicketStatusUpdated::class, NetworkAlertTriggered::class]);

    User::factory()->create(['is_active' => true, 'type' => 'Staf']);
    $asset = InventoryItem::factory()->create([
        'asset_code' => 'INV-PUTI-2026-8801',
        'name' => 'Switch Core Gedung A',
        'location_detail' => 'Rack Server Gedung A Lt. 1',
    ]);

    $outagePayload = [
        'event_type' => 'OUTAGE_DETECTED',
        'incident_id' => 'NING-INC-2026-001',
        'device_name' => 'SW-CORE-GDA',
        'device_ip' => '10.10.1.1',
        'asset_code' => 'INV-PUTI-2026-8801',
    ];

    $rawJson = json_encode($outagePayload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $rawJson, 'test-ning-webhook-secret');

    // 1. Initial OUTAGE_DETECTED creates ticket + broadcasts alert
    $createResponse = $this->withHeader('X-Ning-Signature', $signature)
        ->postJson('/api/v1/ning/webhook', $outagePayload);

    $createResponse->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonPath('deduplicated', false)
        ->assertJsonPath('data.source_channel', 'ning_webhook')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.inventory_item.asset_code', 'INV-PUTI-2026-8801');

    expect(Ticket::where('source_channel', 'ning_webhook')->count())->toBe(1);

    Event::assertDispatched(TicketCreated::class);
    Event::assertDispatched(NetworkAlertTriggered::class, function (NetworkAlertTriggered $event): bool {
        $channels = array_map(static fn (PrivateChannel $ch): string => $ch->name, $event->broadcastOn());

        return $event->broadcastAs() === 'network.alert.triggered'
            && $channels === ['private-noc.tickets']
            && $event->severity === 'critical'
            && $event->deviceName === 'SW-CORE-GDA';
    });

    // 2. Duplicate OUTAGE_DETECTED for same incident deduplicates without creating second ticket
    Event::fake([TicketCreated::class, NetworkAlertTriggered::class]);

    $this->flushHeaders()
        ->withToken('test-ning-webhook-secret')
        ->postJson('/api/v1/ning/webhook', $outagePayload)
        ->assertOk()
        ->assertJsonPath('deduplicated', true);

    expect(Ticket::where('source_channel', 'ning_webhook')->count())->toBe(1);
    Event::assertNotDispatched(TicketCreated::class);
    Event::assertDispatched(NetworkAlertTriggered::class);

    // 3. DEVICE_RESTORED auto-resolves the active NING ticket & broadcasts resolved alert
    Event::fake([TicketStatusUpdated::class, NetworkAlertTriggered::class]);

    $this->withToken('test-ning-webhook-secret')
        ->postJson('/api/v1/ning/webhook', [
            'event_type' => 'DEVICE_RESTORED',
            'incident_id' => 'NING-INC-2026-001',
            'device_name' => 'SW-CORE-GDA',
            'device_ip' => '10.10.1.1',
        ])
        ->assertOk()
        ->assertJsonPath('resolved', true)
        ->assertJsonPath('data.status.value', TicketStatus::Finished->value);

    $ticket = Ticket::where('source_channel', 'ning_webhook')->firstOrFail();
    expect($ticket->status)->toBe(TicketStatus::Finished->value);
    expect($ticket->resolved_at)->not->toBeNull();
    expect($ticket->inventory_item_id)->toBe($asset->id);

    Event::assertDispatched(TicketStatusUpdated::class);
    Event::assertDispatched(NetworkAlertTriggered::class, function (NetworkAlertTriggered $event): bool {
        return $event->severity === 'resolved' && $event->eventType === 'DEVICE_RESTORED';
    });
});

test('ning webhook handles high latency warning with medium priority ticket and warning severity', function (): void {
    Event::fake([NetworkAlertTriggered::class]);

    User::factory()->create(['is_active' => true, 'type' => 'Staf']);

    $this->withToken('test-ning-webhook-secret')
        ->postJson('/api/v1/ning/webhook', [
            'event_type' => 'HIGH_LATENCY_WARNING',
            'device_name' => 'AP-PERPUS-LT2',
            'device_ip' => '10.20.4.12',
            'location' => 'Perpustakaan Lt. 2',
            'latency_ms' => 420.5,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.priority', 'medium');

    Event::assertDispatched(NetworkAlertTriggered::class, function (NetworkAlertTriggered $event): bool {
        $payload = $event->broadcastWith();

        return $payload['severity'] === 'warning'
            && $payload['latency_ms'] === 420.5
            && $payload['device_name'] === 'AP-PERPUS-LT2';
    });
});
