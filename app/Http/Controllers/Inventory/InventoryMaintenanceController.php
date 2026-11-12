<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryCondition;
use App\Enums\MaintenanceStatus;
use App\Http\Controllers\Controller;
use App\Models\BulkMaintenanceBatch;
use App\Models\CampusLocation;
use App\Models\InventoryItem;
use App\Models\InventoryMaintenance;
use App\Models\Ticket;
use App\Services\InventorySyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InventoryMaintenanceController extends Controller
{
    public function __construct(
        public InventorySyncService $inventorySync
    ) {}

    public function index(Request $request): View
    {
        $activeTab = $request->query('tab', 'single');

        // 1. Single Maintenances Query
        $query = InventoryMaintenance::with(['inventoryItem', 'performer', 'ticket', 'bulkBatch'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $maintenances = $query->paginate(15, ['*'], 'maintenance_page')->withQueryString();

        // 2. Bulk Maintenance Batches Query
        $batches = BulkMaintenanceBatch::with(['location', 'performer'])
            ->withCount('maintenances')
            ->latest()
            ->paginate(10, ['*'], 'bulk_page')
            ->withQueryString();

        $inventoryItems = InventoryItem::where('condition', '!=', InventoryCondition::Retired->value)
            ->orderBy('asset_code')
            ->get();

        $activeTickets = Ticket::whereNotIn('status', ['finished', 'canceled'])
            ->orderBy('id', 'desc')
            ->take(50)
            ->get();

        $buildings = CampusLocation::where('type', 'building')->orderBy('name')->get();
        $floors = CampusLocation::where('type', 'floor')->orderBy('name')->get();
        $categories = InventoryItem::CATEGORIES;

        return view('pages.inventory.maintenances.index', compact(
            'activeTab',
            'maintenances',
            'batches',
            'inventoryItems',
            'activeTickets',
            'buildings',
            'floors',
            'categories'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'ticket_id' => ['nullable', 'exists:tickets,id'],
            'type' => ['required', 'in:corrective,preventive,inspection'],
            'status' => ['required', Rule::enum(MaintenanceStatus::class)],
            'result_condition' => ['nullable', Rule::enum(InventoryCondition::class)],
            'title' => ['required', 'string', 'max:255'],
            'action_notes' => ['nullable', 'string', 'max:1000'],
            'auto_resolve_ticket' => ['nullable', 'boolean'],
            'spawn_ticket_on_damage' => ['nullable', 'boolean'],
        ]);

        $now = now();
        $isDone = in_array($validated['status'], [MaintenanceStatus::Completed->value, MaintenanceStatus::Failed->value], true);

        $maintenance = InventoryMaintenance::create([
            'maintenance_code' => InventoryMaintenance::generateMaintenanceCode(),
            'inventory_item_id' => $validated['inventory_item_id'],
            'ticket_id' => $validated['ticket_id'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'result_condition' => $validated['result_condition'] ?? ($isDone ? InventoryCondition::Good->value : null),
            'performed_by' => Auth::id(),
            'title' => $validated['title'],
            'action_notes' => $validated['action_notes'] ?? null,
            'started_at' => $now,
            'completed_at' => $isDone ? $now : null,
        ]);

        $spawnedTicket = $this->inventorySync->syncFromMaintenance(
            $maintenance,
            autoResolveTicket: $request->boolean('auto_resolve_ticket', true),
            spawnTicketOnDamage: $request->boolean('spawn_ticket_on_damage', true),
            actor: Auth::user()
        );

        $msg = "Maintenance {$maintenance->maintenance_code} berhasil dicatat dan disinkronkan ke inventaris!";
        if ($spawnedTicket) {
            $msg .= " Tiket perbaikan otomatis #{$spawnedTicket->ticket_number} telah dibuat.";
        }

        return redirect()->route('inventory.maintenances.index', ['tab' => 'single'])->with('success', $msg);
    }

    public function update(Request $request, InventoryMaintenance $maintenance): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(MaintenanceStatus::class)],
            'result_condition' => ['nullable', Rule::enum(InventoryCondition::class)],
            'action_notes' => ['nullable', 'string', 'max:1000'],
            'auto_resolve_ticket' => ['nullable', 'boolean'],
            'spawn_ticket_on_damage' => ['nullable', 'boolean'],
        ]);

        $now = now();
        $isDone = in_array($validated['status'], [MaintenanceStatus::Completed->value, MaintenanceStatus::Failed->value], true);

        $maintenance->update([
            'status' => $validated['status'],
            'result_condition' => $validated['result_condition'] ?? $maintenance->result_condition ?? ($isDone ? InventoryCondition::Good->value : null),
            'action_notes' => $validated['action_notes'] ?? $maintenance->action_notes,
            'performed_by' => Auth::id(),
            'completed_at' => $isDone ? ($maintenance->completed_at ?? $now) : null,
        ]);

        $spawnedTicket = $this->inventorySync->syncFromMaintenance(
            $maintenance,
            autoResolveTicket: $request->boolean('auto_resolve_ticket', true),
            spawnTicketOnDamage: $request->boolean('spawn_ticket_on_damage', true),
            actor: Auth::user()
        );

        $msg = "Perawatan {$maintenance->maintenance_code} berhasil diperbarui.";
        if ($spawnedTicket) {
            $msg .= " Tiket perbaikan otomatis #{$spawnedTicket->ticket_number} telah dibuat.";
        }

        return redirect()->route('inventory.maintenances.index', ['tab' => 'single'])->with('success', $msg);
    }
}
