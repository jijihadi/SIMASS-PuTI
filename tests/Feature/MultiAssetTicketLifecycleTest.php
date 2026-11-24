<?php

use App\Enums\InventoryCondition;
use App\Enums\MaintenanceStatus;
use App\Enums\TicketStatus;
use App\Events\TicketCreated;
use App\Events\TicketMessagePosted;
use App\Events\TicketStatusUpdated;
use App\Models\InventoryHistory;
use App\Models\InventoryItem;
use App\Models\InventoryMaintenance;
use App\Models\Presence;
use App\Models\Ticket;
use App\Models\User;
use App\Services\InventorySyncService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** @var TestCase $this */
beforeEach(function (): void {
    $this->testKbPath = storage_path('framework/testing/luna-kb-multi-asset-'.uniqid());
});

afterEach(function (): void {
    if (isset($this->testKbPath) && File::isDirectory($this->testKbPath)) {
        File::deleteDirectory($this->testKbPath);
    }
});

test('tiket masuk dengan 4 aset kerja otomatis ter-assign, tersimpan ke pivot, ter-broadcast, dan tampil di NOC serta detail aset', function (): void {
    Event::fake([TicketCreated::class]);

    $reporter = User::factory()->create(['is_active' => true, 'type' => 'Mahasiswa']);
    $technician = User::factory()->create(['is_active' => true, 'type' => 'Staf', 'name' => 'Teknisi On Duty']);

    Presence::create([
        'user_id' => $technician->id,
        'tanggal' => now()->toDateString(),
        'jam_masuk' => '08:00:00',
        'hari' => 'Senin',
    ]);

    $assets = InventoryItem::factory()->count(4)->create([
        'condition' => InventoryCondition::Good->value,
        'status' => 'active',
    ]);

    $response = $this->actingAs($reporter)->post('/ticket', [
        'category' => 'Fasilitas & Lab IT',
        'service_item' => 'Komputer Laboratorium / PC Kerja',
        'title' => '4 Unit PC Lab Jaringan Tidak Bisa Booting Setelah Listrik Padam',
        'description' => 'Empat PC di Lab Jaringan lantai 2 tidak bisa masuk OS setelah pemadaman listrik pagi ini.',
        'location' => 'Lab Jaringan Gedung A Lt. 2',
        'priority' => 'high',
        'inventory_item_ids' => $assets->pluck('id')->all(),
    ]);

    $ticket = Ticket::where('title', '4 Unit PC Lab Jaringan Tidak Bisa Booting Setelah Listrik Padam')->firstOrFail();
    $response->assertRedirect(route('ticket.show', $ticket));

    expect($ticket->status)->toBe(TicketStatus::Open->value);
    expect($ticket->assigned_to)->toBe($technician->id);
    expect($ticket->inventory_item_id)->toBe($assets[0]->id);
    expect($ticket->inventoryItems()->count())->toBe(4);
    expect($ticket->messages()->where('sender_type', 'user')->count())->toBe(1);

    Event::assertDispatched(TicketCreated::class, fn (TicketCreated $e): bool => $e->ticket->id === $ticket->id);

    // Visible on NOC tasks board and ticket detail page with all 4 asset codes
    $this->actingAs($technician)
        ->get('/ticket/tasks')
        ->assertOk()
        ->assertSee($ticket->ticket_number);

    $showResponse = $this->actingAs($technician)
        ->get(route('ticket.show', $ticket))
        ->assertOk()
        ->assertSee('Aset Inventaris Tertaut (4)');

    foreach ($assets as $asset) {
        $showResponse->assertSee($asset->asset_code);

        $this->actingAs($technician)
            ->get(route('inventory.show', $asset))
            ->assertOk()
            ->assertSee($ticket->ticket_number);
    }
});

test('tiket dikerjakan dari open ke in_progress hingga finished memperbaiki 4 aset sekaligus', function (): void {
    Event::fake([TicketStatusUpdated::class, TicketMessagePosted::class]);

    $reporter = User::factory()->create(['is_active' => true, 'type' => 'Mahasiswa']);
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);
    $assets = InventoryItem::factory()->count(4)->create([
        'condition' => InventoryCondition::Good->value,
        'status' => 'active',
    ]);

    $ticket = Ticket::factory()->open()->create([
        'user_id' => $reporter->id,
        'assigned_to' => null,
    ]);
    $ticket->syncInventoryItems($assets->pluck('id')->all());

    // 1. Teknisi mulai mengerjakan tiket (open -> in_progress)
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::InProgress->value,
            'status_note' => 'Sedang dilakukan pengecekan PSU dan OS pada keempat PC.',
        ])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::InProgress->value);
    expect($ticket->assigned_to)->toBe($staff->id);

    Event::assertDispatched(TicketStatusUpdated::class);

    foreach ($assets as $asset) {
        $asset->refresh();
        expect($asset->condition)->toBe(InventoryCondition::Maintenance->value);
        expect(InventoryMaintenance::where('ticket_id', $ticket->id)
            ->where('inventory_item_id', $asset->id)
            ->where('status', MaintenanceStatus::InProgress->value)
            ->exists())->toBeTrue();
        expect(InventoryHistory::where('inventory_item_id', $asset->id)
            ->where('change_type', 'ticket_sync')
            ->exists())->toBeTrue();
    }

    expect(InventoryMaintenance::where('ticket_id', $ticket->id)->count())->toBe(4);

    // 2. Teknisi mengirim balasan progres pengerjaan
    $this->actingAs($staff)
        ->post("/ticket/{$ticket->id}/reply", [
            'message' => 'Keempat perangkat sudah selesai diganti kabel power dan dites booting normal.',
        ])
        ->assertRedirect();

    Event::assertDispatched(TicketMessagePosted::class);

    // 3. Teknisi menyelesaikan tiket (in_progress -> finished)
    Event::fake([TicketStatusUpdated::class]);

    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::Finished->value,
            'status_note' => 'Keempat perangkat kembali berfungsi normal.',
        ])
        ->assertSessionHasNoErrors();

    $ticket->refresh();
    expect($ticket->status)->toBe(TicketStatus::Finished->value);
    expect($ticket->resolved_at)->not->toBeNull();
    Event::assertDispatched(TicketStatusUpdated::class);

    foreach ($assets as $asset) {
        $asset->refresh();
        expect($asset->condition)->toBe(InventoryCondition::Good->value);
        expect($asset->last_maintained_at)->not->toBeNull();
        expect(InventoryMaintenance::where('ticket_id', $ticket->id)
            ->where('inventory_item_id', $asset->id)
            ->where('status', MaintenanceStatus::Completed->value)
            ->where('result_condition', InventoryCondition::Good->value)
            ->exists())->toBeTrue();
    }
});

test('multi-asset ticket failed marks all 4 assets damaged and canceled restores all 4 assets to good', function (): void {
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);

    // Failed scenario on 4 assets
    $failedAssets = InventoryItem::factory()->count(4)->create(['condition' => InventoryCondition::Good->value]);
    $failedTicket = Ticket::factory()->open()->create();
    $failedTicket->syncInventoryItems($failedAssets->pluck('id')->all());

    $this->actingAs($staff)->patch("/ticket/{$failedTicket->id}/status", [
        'status' => TicketStatus::InProgress->value,
    ])->assertSessionHasNoErrors();

    $this->actingAs($staff)->patch("/ticket/{$failedTicket->id}/status", [
        'status' => TicketStatus::Failed->value,
        'status_note' => 'Mainboard keempat perangkat rusak dan butuh pengadaan baru.',
    ])->assertSessionHasNoErrors();

    foreach ($failedAssets as $asset) {
        $asset->refresh();
        expect($asset->condition)->toBe(InventoryCondition::Damaged->value);
        expect(InventoryMaintenance::where('ticket_id', $failedTicket->id)
            ->where('inventory_item_id', $asset->id)
            ->where('status', MaintenanceStatus::Failed->value)
            ->exists())->toBeTrue();
    }

    // Canceled scenario on 4 assets
    $canceledAssets = InventoryItem::factory()->count(4)->create(['condition' => InventoryCondition::Good->value]);
    $canceledTicket = Ticket::factory()->open()->create();
    $canceledTicket->syncInventoryItems($canceledAssets->pluck('id')->all());

    $this->actingAs($staff)->patch("/ticket/{$canceledTicket->id}/status", [
        'status' => TicketStatus::InProgress->value,
    ])->assertSessionHasNoErrors();

    $this->actingAs($staff)->patch("/ticket/{$canceledTicket->id}/status", [
        'status' => TicketStatus::Canceled->value,
        'cancel_reason' => 'user_withdrawn',
        'status_note' => 'Laporan dibatalkan karena kendala listrik gedung sudah normal.',
    ])->assertSessionHasNoErrors();

    foreach ($canceledAssets as $asset) {
        $asset->refresh();
        expect($asset->condition)->toBe(InventoryCondition::Good->value);
        expect(InventoryMaintenance::where('ticket_id', $canceledTicket->id)
            ->where('inventory_item_id', $asset->id)
            ->where('status', MaintenanceStatus::Canceled->value)
            ->exists())->toBeTrue();
    }
});

test('technician can link 4 assets after ticket creation and detach 1 asset cleanly', function (): void {
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);
    $assets = InventoryItem::factory()->count(4)->create(['condition' => InventoryCondition::Good->value]);
    $ticket = Ticket::factory()->inProgress()->create(['assigned_to' => $staff->id]);

    // Link all 4 assets to the in-progress ticket
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/link-inventory", [
            'inventory_item_ids' => $assets->pluck('id')->all(),
        ])
        ->assertRedirect();

    expect($ticket->inventoryItems()->count())->toBe(4);
    foreach ($assets as $asset) {
        expect($asset->fresh()->condition)->toBe(InventoryCondition::Maintenance->value);
    }

    // Detach 1st asset, keep remaining 3
    $remainingIds = $assets->slice(1)->pluck('id')->values()->all();
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/link-inventory", [
            'inventory_item_ids' => $remainingIds,
        ])
        ->assertRedirect();

    $ticket->refresh();
    expect($ticket->inventoryItems()->count())->toBe(3);
    expect($ticket->inventory_item_id)->toBe($remainingIds[0]);

    // Detached asset reverted to good & its maintenance canceled
    expect($assets[0]->fresh()->condition)->toBe(InventoryCondition::Good->value);
    expect(InventoryMaintenance::where('ticket_id', $ticket->id)
        ->where('inventory_item_id', $assets[0]->id)
        ->where('status', MaintenanceStatus::Canceled->value)
        ->exists())->toBeTrue();

    // Remaining 3 assets still in maintenance
    foreach ($assets->slice(1) as $keptAsset) {
        expect($keptAsset->fresh()->condition)->toBe(InventoryCondition::Maintenance->value);
    }
});

test('completing maintenance on 1 of 4 assets does not prematurely resolve multi-asset ticket until all 4 complete', function (): void {
    $staff = User::factory()->create(['is_active' => true, 'type' => 'Staf']);
    $assets = InventoryItem::factory()->count(4)->create(['condition' => InventoryCondition::Good->value]);
    $ticket = Ticket::factory()->open()->create();
    $ticket->syncInventoryItems($assets->pluck('id')->all());

    $this->actingAs($staff)->patch("/ticket/{$ticket->id}/status", [
        'status' => TicketStatus::InProgress->value,
    ])->assertSessionHasNoErrors();

    $syncService = app(InventorySyncService::class);
    $maintenances = InventoryMaintenance::where('ticket_id', $ticket->id)->orderBy('id')->get();
    expect($maintenances)->toHaveCount(4);

    // Complete first 3 maintenances: ticket must remain in_progress
    foreach ($maintenances->take(3) as $mnt) {
        $mnt->update([
            'status' => MaintenanceStatus::Completed->value,
            'result_condition' => InventoryCondition::Good->value,
            'completed_at' => now(),
        ]);
        $syncService->syncFromMaintenance($mnt, autoResolveTicket: true, actor: $staff);

        expect($ticket->fresh()->status)->toBe(TicketStatus::InProgress->value);
    }

    // Complete 4th (final) maintenance: ticket must auto-resolve to finished
    $lastMnt = $maintenances->last();
    $lastMnt->update([
        'status' => MaintenanceStatus::Completed->value,
        'result_condition' => InventoryCondition::Good->value,
        'completed_at' => now(),
    ]);
    $syncService->syncFromMaintenance($lastMnt, autoResolveTicket: true, actor: $staff);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Finished->value);
});

test('luna whatsapp api supports linking 4 assets via asset_codes and returns inventory_items resource', function (): void {
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

    $assets = collect([1, 2, 3, 4])->map(fn (int $idx): InventoryItem => InventoryItem::factory()->create([
        'asset_code' => "INV-PUTI-2026-900{$idx}",
        'name' => "Access Point Koridor {$idx}",
    ]));

    $response = $this->withToken('test-secret-luna-key')
        ->postJson('/api/v1/luna/tickets', [
            'phone' => '081234567890',
            'description' => 'Keempat access point di koridor lantai 3 mati bersamaan sejak pagi.',
            'location' => 'Koridor Gedung A Lt. 3',
            'priority' => 'high',
            'asset_codes' => $assets->pluck('asset_code')->all(),
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('success', true)
        ->assertJsonCount(4, 'data.inventory_items')
        ->assertJsonPath('data.inventory_item.asset_code', 'INV-PUTI-2026-9001');

    $ticket = Ticket::where('ticket_number', $response->json('data.ticket_number'))->firstOrFail();
    expect($ticket->inventoryItems()->count())->toBe(4);
});

