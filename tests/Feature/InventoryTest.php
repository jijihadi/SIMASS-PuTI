<?php

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\TicketStatus;
use App\Models\BulkMaintenanceBatch;
use App\Models\CampusLocation;
use App\Models\InventoryCategory;
use App\Models\InventoryConditionLookup;
use App\Models\InventoryHistory;
use App\Models\InventoryItem;
use App\Models\InventoryMaintenance;
use App\Models\InventoryStatusLookup;
use App\Models\InventoryType;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** @var TestCase $this */
test('ticket status transitions synchronize inventory item condition and maintenance logs', function () {
    $staff = User::factory()->create(['type' => 'Staf']);
    $item = InventoryItem::factory()->create([
        'condition' => InventoryCondition::Good->value,
    ]);

    $ticket = Ticket::factory()->open()->create([
        'user_id' => $staff->id,
        'inventory_item_id' => $item->id,
    ]);

    // 1. Move ticket to in_progress -> item condition becomes maintenance & maintenance log is created
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::InProgress->value,
        ])
        ->assertSessionHasNoErrors();

    $item->refresh();
    expect($item->condition)->toBe(InventoryCondition::Maintenance->value);

    $maintenance = InventoryMaintenance::where('ticket_id', $ticket->id)->first();
    expect($maintenance)->not->toBeNull();
    expect($maintenance->status)->toBe(MaintenanceStatus::InProgress->value);

    // 2. Move ticket to failed -> item condition becomes damaged & maintenance log is marked failed
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::Failed->value,
            'status_note' => 'Motherboard PC terbakar dan butuh penggantian unit.',
        ])
        ->assertSessionHasNoErrors();

    $item->refresh();
    $maintenance->refresh();
    expect($item->condition)->toBe(InventoryCondition::Damaged->value);
    expect($maintenance->status)->toBe(MaintenanceStatus::Failed->value);

    // 3. Reopen ticket to in_progress -> item condition back to maintenance
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::InProgress->value,
        ])
        ->assertSessionHasNoErrors();

    $item->refresh();
    expect($item->condition)->toBe(InventoryCondition::Maintenance->value);

    // 4. Finish ticket -> item condition becomes good & last_maintained_at is set
    $this->actingAs($staff)
        ->patch("/ticket/{$ticket->id}/status", [
            'status' => TicketStatus::Finished->value,
            'status_note' => 'Motherboard baru telah dipasang dan diuji normal.',
        ])
        ->assertSessionHasNoErrors();

    $item->refresh();
    $maintenance->refresh();
    expect($item->condition)->toBe(InventoryCondition::Good->value);
    expect($item->last_maintained_at)->not->toBeNull();
    expect($maintenance->status)->toBe(MaintenanceStatus::Completed->value);
});

test('completing single maintenance can auto-resolve linked ticket or spawn ticket on damage', function () {
    $staff = User::factory()->create(['type' => 'Staf']);
    $item1 = InventoryItem::factory()->maintenance()->create();
    $ticket = Ticket::factory()->inProgress()->create([
        'user_id' => $staff->id,
        'inventory_item_id' => $item1->id,
    ]);

    // Log completed maintenance linked to active ticket -> auto-finishes ticket
    $this->actingAs($staff)
        ->post('/inventory/maintenances', [
            'inventory_item_id' => $item1->id,
            'ticket_id' => $ticket->id,
            'type' => 'corrective',
            'status' => MaintenanceStatus::Completed->value,
            'result_condition' => InventoryCondition::Good->value,
            'title' => 'Penggantian Power Supply PC Lab',
            'action_notes' => 'PSU diganti 500W baru.',
            'auto_resolve_ticket' => true,
        ])
        ->assertRedirect();

    $ticket->refresh();
    $item1->refresh();
    expect($ticket->status)->toBe(TicketStatus::Finished->value);
    expect($item1->condition)->toBe(InventoryCondition::Good->value);

    // Log inspection maintenance that discovers damage -> auto-spawns corrective ticket
    $item2 = InventoryItem::factory()->create();
    $this->actingAs($staff)
        ->post('/inventory/maintenances', [
            'inventory_item_id' => $item2->id,
            'type' => 'inspection',
            'status' => MaintenanceStatus::Failed->value,
            'result_condition' => InventoryCondition::Damaged->value,
            'title' => 'Inspeksi Proyektor Kelas',
            'action_notes' => 'Lampu proyektor redup dan bergaris.',
            'spawn_ticket_on_damage' => true,
        ])
        ->assertRedirect();

    $item2->refresh();
    expect($item2->condition)->toBe(InventoryCondition::Damaged->value);

    $spawnedTicket = Ticket::where('inventory_item_id', $item2->id)->first();
    expect($spawnedTicket)->not->toBeNull();
    expect($spawnedTicket->status)->toBe(TicketStatus::Open->value);
});

test('bulk maintenance batch updates all items and spawns tickets for damaged items when opted in', function () {
    $staff = User::factory()->create(['type' => 'Staf']);
    $itemGood = InventoryItem::factory()->create(['name' => 'PC Lab #01']);
    $itemDamaged = InventoryItem::factory()->create(['name' => 'PC Lab #02']);

    // 1. Create bulk maintenance batch
    $this->actingAs($staff)
        ->post('/inventory/bulk-maintenances', [
            'title' => 'Maintenance Massal Lab Komputer 1',
            'maintenance_type' => 'preventive',
            'notes' => 'Pengecekan jelang UAS.',
            'item_ids' => [$itemGood->id, $itemDamaged->id],
        ])
        ->assertRedirect();

    $batch = BulkMaintenanceBatch::first();
    expect($batch)->not->toBeNull();
    expect($batch->maintenances)->toHaveCount(2);

    $itemGood->refresh();
    $itemDamaged->refresh();
    expect($itemGood->condition)->toBe(InventoryCondition::Maintenance->value);
    expect($itemDamaged->condition)->toBe(InventoryCondition::Maintenance->value);

    // 2. Complete batch: item 1 is good, item 2 is damaged -> spawns 1 ticket
    $mntGood = $batch->maintenances()->where('inventory_item_id', $itemGood->id)->first();
    $mntDamaged = $batch->maintenances()->where('inventory_item_id', $itemDamaged->id)->first();

    $this->actingAs($staff)
        ->patch("/inventory/bulk-maintenances/{$batch->id}/complete", [
            'outcomes' => [
                $mntGood->id => [
                    'result_condition' => InventoryCondition::Good->value,
                    'action_notes' => 'Normal',
                ],
                $mntDamaged->id => [
                    'result_condition' => InventoryCondition::Damaged->value,
                    'action_notes' => 'RAM error beep 3x',
                ],
            ],
            'spawn_ticket_on_damage' => true,
        ])
        ->assertRedirect();

    $batch->refresh();
    $itemGood->refresh();
    $itemDamaged->refresh();

    expect($batch->status)->toBe(MaintenanceStatus::Completed->value);
    expect($itemGood->condition)->toBe(InventoryCondition::Good->value);
    expect($itemDamaged->condition)->toBe(InventoryCondition::Damaged->value);

    $spawnedTicket = Ticket::where('inventory_item_id', $itemDamaged->id)->first();
    expect($spawnedTicket)->not->toBeNull();
    expect($spawnedTicket->source_channel)->toBe('bulk_maintenance');
});

test('can create digital software license asset and track renewal date and access details', function () {
    $staff = User::factory()->create(['type' => 'Staf']);

    $response = $this->actingAs($staff)->post('/inventory', [
        'name' => 'Lisensi JetBrains All Products Pack',
        'category' => 'Digital & Lisensi Software',
        'asset_type' => 'digital',
        'condition' => InventoryCondition::Good->value,
        'status' => InventoryStatus::Active->value,
        'billing_cycle' => 'yearly',
        'renewal_date' => now()->addYear()->format('Y-m-d'),
        'access_url' => 'https://account.jetbrains.com',
        'access_recipients' => 'Tim Dosen Pemrograman & PuTI',
        'notes' => 'Lisensi pendidikan 25 seats.',
    ]);

    $response->assertRedirect('/inventory');

    $item = InventoryItem::where('name', 'Lisensi JetBrains All Products Pack')->first();
    expect($item)->not->toBeNull();
    expect($item->asset_type)->toBe('digital');
    expect($item->billing_cycle)->toBe('yearly');
    expect($item->access_url)->toBe('https://account.jetbrains.com');
    expect($item->histories)->toHaveCount(1);
    expect($item->histories->first()->change_type)->toBe('created');
});

test('can download inventory template and import assets via csv file with history tracking', function () {
    $staff = User::factory()->create(['type' => 'Staf']);

    // 1. Template download
    $templateRes = $this->actingAs($staff)->get('/inventory/template');
    $templateRes->assertOk();
    $templateRes->assertHeader('content-type', 'text/csv; charset=UTF-8');

    // 2. CSV Import
    $csvContent = implode("\n", [
        'kode_aset,nama,kategori,tipe,status,kondisi,gedung,lantai,lokasi_detail,merk_model,serial_number,siklus_lisensi,tanggal_perbaruan,alamat_akses,penerima_akses,keterangan',
        ',PC Lab Import Test 01,Komputer & PC Lab,fisik,active,good,,,Lab 101,HP ProDesk,SN-HP-001,,,,Imported PC',
        ',Lisensi Slack Pro PuTI,Digital & Lisensi Software,digital,active,good,,,,,,monthly,2026-12-01,https://slack.com,Seluruh Staf,Team comms',
    ]);

    $file = UploadedFile::fake()->createWithContent('test_inventory.csv', $csvContent);

    $importRes = $this->actingAs($staff)->post('/inventory/import', [
        'file' => $file,
    ]);

    $importRes->assertRedirect('/inventory');

    $pc = InventoryItem::where('name', 'PC Lab Import Test 01')->first();
    expect($pc)->not->toBeNull();
    expect($pc->asset_type)->toBe('physical');
    expect($pc->histories)->toHaveCount(1);
    expect($pc->histories->first()->change_type)->toBe('bulk_import');

    $license = InventoryItem::where('name', 'Lisensi Slack Pro PuTI')->first();
    expect($license)->not->toBeNull();
    expect($license->asset_type)->toBe('digital');
    expect($license->billing_cycle)->toBe('monthly');
    expect($license->renewal_date->format('Y-m-d'))->toBe('2026-12-01');
});

test('can mutate location, status, and condition for multiple assets with audit history', function () {
    $staff = User::factory()->create(['type' => 'Staf']);

    $building = CampusLocation::create(['type' => 'building', 'name' => 'Gedung Rektorat', 'code' => 'REK']);
    $floor = CampusLocation::create(['type' => 'floor', 'name' => 'Lantai 3', 'code' => 'LT3', 'parent_id' => $building->id]);

    $item1 = InventoryItem::factory()->create([
        'status' => InventoryStatus::Spare->value,
        'condition' => InventoryCondition::Good->value,
        'location_detail' => 'Gudang Lama',
    ]);

    $item2 = InventoryItem::factory()->create([
        'status' => InventoryStatus::Spare->value,
        'condition' => InventoryCondition::Good->value,
        'location_detail' => 'Gudang Lama',
    ]);

    $response = $this->actingAs($staff)
        ->from('/inventory/mutations')
        ->post('/inventory/mutate', [
            'item_ids' => [$item1->id, $item2->id],
            'new_building_id' => $building->id,
            'new_floor_id' => $floor->id,
            'new_location_detail' => 'Ruang Staff PuTI Meja 05 & 06',
            'new_status' => InventoryStatus::InUse->value,
            'new_condition' => InventoryCondition::Good->value,
            'mutation_notes' => 'Mutasi perangkat dari gudang ke ruang staf operasional semester baru.',
        ]);

    $response->assertRedirect('/inventory/mutations');

    $item1->refresh();
    $item2->refresh();

    expect($item1->building_id)->toBe($building->id);
    expect($item1->floor_id)->toBe($floor->id);
    expect($item1->status)->toBe(InventoryStatus::InUse->value);
    expect($item1->location_detail)->toBe('Ruang Staff PuTI Meja 05 & 06');

    $history = InventoryHistory::where('inventory_item_id', $item1->id)->where('change_type', 'mutation')->first();
    expect($history)->not->toBeNull();
    expect($history->old_status)->toBe(InventoryStatus::Spare->value);
    expect($history->new_status)->toBe(InventoryStatus::InUse->value);
    expect($history->notes)->toBe('Mutasi perangkat dari gudang ke ruang staf operasional semester baru.');
});

test('unified maintenance page loads both single and bulk tabs without error', function () {
    $staff = User::factory()->create(['type' => 'Staf']);

    // Visit single tab
    $resSingle = $this->actingAs($staff)->get('/inventory/maintenances?tab=single');
    $resSingle->assertOk();
    $resSingle->assertSee('Maintenance Satuan');

    // Visit bulk tab
    $resBulk = $this->actingAs($staff)->get('/inventory/maintenances?tab=bulk');
    $resBulk->assertOk();
    $resBulk->assertSee('Bulk Maintenance Massal');
});

test('mutations management page loads successfully', function () {
    $staff = User::factory()->create(['type' => 'Staf']);

    $res = $this->actingAs($staff)->get('/inventory/mutations');
    $res->assertOk();
    $res->assertSee('Mutasi Lokasi');
});

test('dedicated bulk input page loads successfully', function () {
    $staff = User::factory()->create(['type' => 'Staf']);

    $res = $this->actingAs($staff)->get('/inventory/bulk-input');
    $res->assertOk();
    $res->assertSee('Bulk Input Aset Inventaris');
});

test('admin can manage inventory categories in database and inventory uses them dynamically', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create(['type' => 'Staf', 'is_active' => true]);
    $admin->assignRole('super-admin');

    $res = $this->actingAs($admin)->post('/admin/inventory-categories', [
        'name' => 'Perangkat Smart TV & Display',
        'code' => 'STV',
        'description' => 'Smart TV 65 inch dan video wall interaktif.',
    ]);

    $res->assertRedirect('/admin/master-data?tab=inventory-categories');

    expect(InventoryCategory::where('code', 'STV')->exists())->toBeTrue();
    expect(InventoryItem::getCategories())->toContain('Perangkat Smart TV & Display');

    // Test Edit Category with cascade to items
    $category = InventoryCategory::where('code', 'STV')->first();
    $item = InventoryItem::factory()->create([
        'category' => 'Perangkat Smart TV & Display',
    ]);

    $editRes = $this->actingAs($admin)->put("/admin/inventory-categories/{$category->id}", [
        'name' => 'Smart Display & Interactive TV',
        'code' => 'STV-NEW',
        'description' => 'Updated description.',
        'is_active' => true,
    ]);
    $editRes->assertRedirect('/admin/master-data?tab=inventory-categories');

    $category->refresh();
    expect($category->name)->toBe('Smart Display & Interactive TV');
    expect($category->code)->toBe('STV-NEW');

    // Verify cascade update to items
    $item->refresh();
    expect($item->category)->toBe('Smart Display & Interactive TV');
});

test('admin can manage inventory types, statuses, and conditions in master data', function () {
    Role::findOrCreate('super-admin', 'web');
    $admin = User::factory()->create(['type' => 'Staf', 'is_active' => true]);
    $admin->assignRole('super-admin');

    // 1. Types
    $resType = $this->actingAs($admin)->post('/admin/inventory-types', [
        'name' => 'Virtual Machine / VPS',
        'code' => 'vm',
        'description' => 'Mesin virtual cloud PuTI.',
    ]);
    $resType->assertRedirect('/admin/master-data?tab=inventory-types');
    $vmType = InventoryType::where('code', 'vm')->first();
    expect($vmType)->not->toBeNull();

    $this->actingAs($admin)->put("/admin/inventory-types/{$vmType->id}", [
        'name' => 'Cloud VM & Container',
        'code' => 'vm',
        'description' => 'Docker, K8s, dan VPS.',
        'is_active' => true,
    ])->assertRedirect('/admin/master-data?tab=inventory-types');
    $vmType->refresh();
    expect($vmType->name)->toBe('Cloud VM & Container');

    // 2. Statuses
    $resStatus = $this->actingAs($admin)->post('/admin/inventory-statuses', [
        'name' => 'Dipinjamkan Sementara',
        'code' => 'loaned',
        'color' => 'blue',
    ]);
    $resStatus->assertRedirect('/admin/master-data?tab=inventory-statuses');
    $status = InventoryStatusLookup::where('code', 'loaned')->first();
    expect($status)->not->toBeNull();

    $this->actingAs($admin)->put("/admin/inventory-statuses/{$status->id}", [
        'name' => 'Dipinjamkan Unit Luar',
        'code' => 'loaned',
        'color' => 'purple',
        'is_active' => true,
    ])->assertRedirect('/admin/master-data?tab=inventory-statuses');
    $status->refresh();
    expect($status->name)->toBe('Dipinjamkan Unit Luar');
    expect($status->color)->toBe('purple');

    // 3. Conditions
    $resCondition = $this->actingAs($admin)->post('/admin/inventory-conditions', [
        'name' => 'Hilang / Tidak Ditemukan',
        'code' => 'missing',
        'color' => 'rose',
    ]);
    $resCondition->assertRedirect('/admin/master-data?tab=inventory-conditions');
    $condition = InventoryConditionLookup::where('code', 'missing')->first();
    expect($condition)->not->toBeNull();

    $this->actingAs($admin)->put("/admin/inventory-conditions/{$condition->id}", [
        'name' => 'Hilang / Investigasi',
        'code' => 'missing',
        'color' => 'rose',
        'is_active' => true,
    ])->assertRedirect('/admin/master-data?tab=inventory-conditions');
    $condition->refresh();
    expect($condition->name)->toBe('Hilang / Investigasi');
});

test('inventory index displays compact table with jenis below name, maintenance cycle, and reminder badges', function () {
    $staff = User::factory()->create(['type' => 'Staf']);

    // 1. Physical asset with coming soon maintenance
    $physical = InventoryItem::factory()->create([
        'name' => 'Workstation Lab AI #01',
        'asset_type' => 'physical',
        'maintenance_cycle' => 'quarterly',
        'next_maintenance_date' => now()->addDays(5),
    ]);

    // 2. Digital license with coming soon renewal
    $digital = InventoryItem::factory()->create([
        'name' => 'Lisensi Cloud LMS PuTI',
        'asset_type' => 'digital',
        'billing_cycle' => 'yearly',
        'renewal_date' => now()->addDays(10),
    ]);

    $res = $this->actingAs($staff)->get('/inventory');
    $res->assertOk();

    // Headers check
    $res->assertSee('Kode');
    $res->assertSee('Nama & Info Aset', false);
    $res->assertSee('Status & Kondisi', false);

    // Physical asset badges
    $res->assertSee('Workstation Lab AI #01');
    $res->assertSee('Siklus Rawat: Triwulan (3 Bulan)');
    $res->assertSee('Perlu Maintenance (H-5');

    // Digital license badges
    $res->assertSee('Lisensi Cloud LMS PuTI');
    $res->assertSee('Digital / Lisensi');
    $res->assertSee('Siklus: Tahunan');
    $res->assertSee('Segera Diperbarui (H-10');
});

test('sidebar renders inventory master data and bulk input menu items and inventory master data route works', function () {
    $staff = User::factory()->create(['type' => 'Staf', 'is_active' => true]);

    // Check inventory index sidebar includes the new menu items
    $res = $this->actingAs($staff)->get('/inventory');
    $res->assertOk();
    $res->assertSee('Bulk Input Aset');
    $res->assertSee('Kategori &amp; Data Master', false);

    // Check accessing inventory master data directly
    $resMaster = $this->actingAs($staff)->get('/inventory/master-data');
    $resMaster->assertOk();
    $resMaster->assertSee('Kategori &amp; Data Master Aset', false);
    $resMaster->assertSee('Kategori Inventaris');
    $resMaster->assertSee('Kembali ke Inventaris');

    // Test updating a category with referer from inventory/master-data redirects to inventory.master-data
    $admin = User::factory()->create(['type' => 'Staf', 'is_active' => true]);
    $adminRole = Role::firstOrCreate(['name' => 'super-admin']);
    $admin->assignRole($adminRole);

    $category = InventoryCategory::firstOrCreate(
        ['code' => 'TEST-CAT'],
        ['name' => 'Test Category Sidebar', 'description' => 'Perangkat test', 'is_active' => true]
    );

    $this->actingAs($admin)
        ->from('/inventory/master-data?tab=inventory-categories')
        ->put("/admin/inventory-categories/{$category->id}", [
            'name' => 'Test Category Updated',
            'code' => 'TEST-CAT-UPD',
            'description' => 'Perangkat test updated',
            'is_active' => true,
        ])
        ->assertRedirect('/inventory/master-data?tab=inventory-categories');
});
