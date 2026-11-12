<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryCondition;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Models\BulkMaintenanceBatch;
use App\Models\CampusLocation;
use App\Models\InventoryItem;
use App\Models\InventoryMaintenance;
use App\Services\InventorySyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BulkMaintenanceController extends Controller
{
    public function __construct(
        public InventorySyncService $inventorySync
    ) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('inventory.maintenances.index', ['tab' => 'bulk']);
    }

    public function create(Request $request): View
    {
        $query = InventoryItem::with(['campusLocation', 'building', 'floor'])
            ->where('condition', '!=', InventoryCondition::Retired->value)
            ->orderBy('asset_code');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        if ($request->filled('floor_id')) {
            $query->where('floor_id', $request->floor_id);
        }

        if ($request->filled('campus_location_id')) {
            $query->where('campus_location_id', $request->campus_location_id);
        }

        $items = $query->get();
        $categories = InventoryItem::CATEGORIES;
        $buildings = CampusLocation::where('type', 'building')->orderBy('name')->get();
        $floors = CampusLocation::where('type', 'floor')->orderBy('name')->get();
        $locations = CampusLocation::orderBy('name')->get();

        return view('pages.inventory.bulk.create', compact('items', 'categories', 'buildings', 'floors', 'locations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'maintenance_type' => ['required', 'in:preventive,inspection,upgrade'],
            'campus_location_id' => ['nullable', 'exists:campus_locations,id'],
            'category_filter' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:inventory_items,id'],
        ]);

        $now = now();
        $batch = BulkMaintenanceBatch::create([
            'batch_code' => BulkMaintenanceBatch::generateBatchCode(),
            'title' => $validated['title'],
            'maintenance_type' => $validated['maintenance_type'],
            'campus_location_id' => $validated['campus_location_id'] ?? null,
            'category_filter' => $validated['category_filter'] ?? null,
            'status' => MaintenanceStatus::InProgress->value,
            'scheduled_at' => $now,
            'performed_by' => Auth::id(),
            'notes' => $validated['notes'] ?? null,
        ]);

        $items = InventoryItem::whereIn('id', $validated['item_ids'])->get();
        foreach ($items as $item) {
            $oldState = [
                'location' => $item->display_location,
                'status' => $item->status,
                'condition' => $item->condition,
            ];

            $item->update([
                'condition' => InventoryCondition::Maintenance->value,
            ]);

            $item->recordHistory(
                changeType: 'maintenance',
                oldState: $oldState,
                notes: "Masuk ke dalam penugasan Bulk Maintenance {$batch->batch_code}: {$batch->title}",
                userId: Auth::id()
            );

            InventoryMaintenance::create([
                'maintenance_code' => InventoryMaintenance::generateMaintenanceCode(),
                'inventory_item_id' => $item->id,
                'bulk_batch_id' => $batch->id,
                'type' => $validated['maintenance_type'] === 'upgrade' ? 'preventive' : $validated['maintenance_type'],
                'status' => MaintenanceStatus::InProgress->value,
                'performed_by' => Auth::id(),
                'title' => "{$batch->title} — {$item->name}",
                'action_notes' => $validated['notes'] ?? null,
                'started_at' => $now,
            ]);
        }

        return redirect()->route('inventory.bulk.show', $batch)
            ->with('success', "Bulk Maintenance {$batch->batch_code} dengan {$items->count()} aset berhasil dimulai!");
    }

    public function show(BulkMaintenanceBatch $batch): View
    {
        $batch->load(['campusLocation', 'performer', 'maintenances.inventoryItem', 'maintenances.ticket']);

        return view('pages.inventory.bulk.show', compact('batch'));
    }

    public function complete(Request $request, BulkMaintenanceBatch $batch): RedirectResponse
    {
        $validated = $request->validate([
            'outcomes' => ['required', 'array'],
            'outcomes.*.result_condition' => ['required', 'in:good,damaged,retired'],
            'outcomes.*.action_notes' => ['nullable', 'string', 'max:500'],
            'spawn_ticket_on_damage' => ['nullable', 'boolean'],
        ]);

        $spawnTickets = $request->boolean('spawn_ticket_on_damage', false);

        $spawnedTickets = $this->inventorySync->completeBulkBatch(
            $batch,
            $validated['outcomes'],
            spawnTicketOnDamage: $spawnTickets,
            actor: Auth::user()
        );

        $msg = "Bulk Maintenance {$batch->batch_code} selesai! Kondisi seluruh aset telah disinkronkan.";
        if (count($spawnedTickets) > 0) {
            $msg .= ' '.count($spawnedTickets).' tiket perbaikan baru otomatis dibuat untuk aset yang rusak.';
        }

        return redirect()->route('inventory.bulk.show', $batch)
            ->with('success', $msg);
    }
}
