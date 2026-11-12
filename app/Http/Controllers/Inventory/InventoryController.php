<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Http\Controllers\Controller;
use App\Models\CampusLocation;
use App\Models\InventoryHistory;
use App\Models\InventoryItem;
use App\Services\InventoryImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InventoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = InventoryItem::with(['campusLocation', 'building', 'floor'])->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('brand_model', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('location_detail', 'like', "%{$search}%")
                    ->orWhere('access_url', 'like', "%{$search}%")
                    ->orWhere('access_recipients', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('asset_type')) {
            $query->where('asset_type', $request->asset_type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        if ($request->filled('floor_id')) {
            $query->where('floor_id', $request->floor_id);
        }

        $items = $query->paginate(15)->withQueryString();

        $stats = [
            'total' => InventoryItem::count(),
            'physical' => InventoryItem::where('asset_type', 'physical')->count(),
            'digital' => InventoryItem::where('asset_type', 'digital')->count(),
            'good' => InventoryItem::where('condition', InventoryCondition::Good->value)->count(),
            'maintenance' => InventoryItem::where('condition', InventoryCondition::Maintenance->value)->count(),
            'damaged' => InventoryItem::where('condition', InventoryCondition::Damaged->value)->count(),
        ];

        $categories = InventoryItem::getCategories();
        $assetTypes = InventoryItem::getAssetTypes();
        $buildings = CampusLocation::where('type', 'building')->orderBy('name')->get();
        $floors = CampusLocation::where('type', 'floor')->orderBy('name')->get();
        $locations = CampusLocation::orderBy('name')->get();
        $statuses = InventoryItem::getStatusLookups();
        $conditions = InventoryItem::getConditionLookups();

        return view('pages.inventory.index', compact(
            'items',
            'stats',
            'categories',
            'assetTypes',
            'buildings',
            'floors',
            'locations',
            'statuses',
            'conditions'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'asset_code' => ['nullable', 'string', 'max:50', 'unique:inventory_items,asset_code'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'asset_type' => ['required', 'string', 'in:physical,digital'],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'building_id' => ['nullable', 'exists:campus_locations,id'],
            'floor_id' => ['nullable', 'exists:campus_locations,id'],
            'campus_location_id' => ['nullable', 'exists:campus_locations,id'],
            'location_detail' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', Rule::enum(InventoryCondition::class)],
            'status' => ['required', Rule::enum(InventoryStatus::class)],
            'renewal_date' => ['nullable', 'date'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
            'access_url' => ['nullable', 'string', 'max:500'],
            'access_recipients' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($validated['asset_code'])) {
            $validated['asset_code'] = InventoryItem::generateAssetCode();
        }

        $item = InventoryItem::create($validated);

        $item->recordHistory(
            changeType: 'created',
            oldState: [],
            notes: 'Pendaftaran aset baru ke sistem.',
            userId: auth()->id()
        );

        return redirect()->route('inventory.index')
            ->with('success', "Aset {$item->asset_code} ({$item->name}) berhasil ditambahkan!");
    }

    public function show(InventoryItem $item): View
    {
        $item->load([
            'campusLocation',
            'building',
            'floor',
            'histories.user',
            'tickets.user',
            'tickets.assignee',
            'maintenances.performer',
            'maintenances.ticket',
            'maintenances.bulkBatch',
        ]);

        $categories = InventoryItem::getCategories();
        $assetTypes = InventoryItem::getAssetTypes();
        $buildings = CampusLocation::where('type', 'building')->orderBy('name')->get();
        $floors = CampusLocation::where('type', 'floor')->orderBy('name')->get();
        $locations = CampusLocation::orderBy('name')->get();
        $statuses = InventoryItem::getStatusLookups();
        $conditions = InventoryItem::getConditionLookups();

        return view('pages.inventory.show', compact(
            'item',
            'categories',
            'assetTypes',
            'buildings',
            'floors',
            'locations',
            'statuses',
            'conditions'
        ));
    }

    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'asset_type' => ['required', 'string', 'in:physical,digital'],
            'brand_model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'building_id' => ['nullable', 'exists:campus_locations,id'],
            'floor_id' => ['nullable', 'exists:campus_locations,id'],
            'campus_location_id' => ['nullable', 'exists:campus_locations,id'],
            'location_detail' => ['nullable', 'string', 'max:255'],
            'condition' => ['required', Rule::enum(InventoryCondition::class)],
            'status' => ['required', Rule::enum(InventoryStatus::class)],
            'renewal_date' => ['nullable', 'date'],
            'billing_cycle' => ['nullable', 'string', 'in:monthly,yearly'],
            'access_url' => ['nullable', 'string', 'max:500'],
            'access_recipients' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'history_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $oldState = [
            'location' => $item->display_location,
            'status' => $item->status,
            'condition' => $item->condition,
        ];

        $historyNotes = $validated['history_notes'] ?? null;
        unset($validated['history_notes']);

        $item->update($validated);

        // Record history snapshot
        $item->refresh();
        $hasLocationChange = ($oldState['location'] ?? null) !== $item->display_location;
        $hasStatusChange = ($oldState['status'] ?? null) !== $item->status;
        $hasConditionChange = ($oldState['condition'] ?? null) !== $item->condition;

        if ($hasLocationChange || $hasStatusChange || $hasConditionChange || ! empty($historyNotes)) {
            $notes = $historyNotes ?: 'Pembaruan informasi aset.';
            $item->recordHistory(
                changeType: 'status_condition',
                oldState: $oldState,
                notes: $notes,
                userId: auth()->id()
            );
        }

        return back()->with('success', "Data aset {$item->asset_code} berhasil diperbarui.");
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $code = $item->asset_code;
        $item->delete();

        return redirect()->route('inventory.index')
            ->with('success', "Aset {$code} berhasil dihapus.");
    }

    /**
     * Download standard Excel/CSV template.
     */
    public function downloadTemplate(InventoryImportService $importer): Response
    {
        $content = $importer->generateCsvTemplate();
        $filename = 'template_inventaris_puti_'.now()->format('Ymd_His').'.csv';

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Handle Excel / CSV import.
     */
    public function importExcel(Request $request, InventoryImportService $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv', 'txt'], true)) {
            return back()->with('error', 'Format file tidak didukung. Harap unggah file .xlsx atau .csv.');
        }

        try {
            $rows = $importer->parseUploadedFile($file);
            if (empty($rows)) {
                return back()->with('error', 'File tidak berisi data atau baris data kosong.');
            }

            $userId = auth()->id() ?? 1;
            $result = $importer->importRows($rows, $userId);

            $message = "Berhasil mengimpor {$result['imported']} aset.";
            if (! empty($result['errors'])) {
                $errorMsg = implode(' ', array_slice($result['errors'], 0, 5));
                if (count($result['errors']) > 5) {
                    $errorMsg .= ' (dan '.(count($result['errors']) - 5).' catatan lainnya)';
                }

                return redirect()->route('inventory.index')
                    ->with('success', $message)
                    ->with('warning', "Catatan impor: {$errorMsg}");
            }

            return redirect()->route('inventory.index')->with('success', $message);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses file impor: '.$e->getMessage());
        }
    }

    /**
     * Show dedicated full page for multi-row bulk asset input.
     */
    public function bulkCreate(): View
    {
        $categories = InventoryItem::getCategories();
        $assetTypes = InventoryItem::getAssetTypes();
        $buildings = CampusLocation::where('type', 'building')->orderBy('name')->get();
        $floors = CampusLocation::where('type', 'floor')->orderBy('name')->get();
        $statuses = InventoryItem::getStatusLookups();
        $conditions = InventoryItem::getConditionLookups();

        return view('pages.inventory.bulk-input', compact(
            'categories',
            'assetTypes',
            'buildings',
            'floors',
            'statuses',
            'conditions'
        ));
    }

    /**
     * Bulk store multiple items from multi-row UI.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.category' => ['required', 'string', 'max:100'],
            'items.*.asset_type' => ['required', 'string', 'in:physical,digital'],
            'items.*.brand_model' => ['nullable', 'string', 'max:150'],
            'items.*.serial_number' => ['nullable', 'string', 'max:100'],
            'items.*.building_id' => ['nullable', 'exists:campus_locations,id'],
            'items.*.floor_id' => ['nullable', 'exists:campus_locations,id'],
            'items.*.location_detail' => ['nullable', 'string', 'max:255'],
            'items.*.condition' => ['required', Rule::enum(InventoryCondition::class)],
            'items.*.status' => ['required', Rule::enum(InventoryStatus::class)],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = auth()->id();
        $count = 0;

        foreach ($validated['items'] as $itemData) {
            $itemData['asset_code'] = InventoryItem::generateAssetCode();
            $item = InventoryItem::create($itemData);

            $item->recordHistory(
                changeType: 'bulk_import',
                oldState: [],
                notes: 'Pendaftaran aset melalui Bulk Input tabel web.',
                userId: $userId
            );

            $count++;
        }

        return redirect()->route('inventory.index')
            ->with('success', "Berhasil menambahkan {$count} aset secara serentak!");
    }

    /**
     * Display the dedicated asset mutations management page.
     */
    public function mutationsIndex(Request $request): View
    {
        $query = InventoryItem::with(['campusLocation', 'building', 'floor'])
            ->where('asset_type', 'physical')
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('brand_model', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%")
                    ->orWhere('location_detail', 'like', "%{$search}%");
            });
        }

        if ($request->filled('building_id')) {
            $query->where('building_id', $request->building_id);
        }

        if ($request->filled('floor_id')) {
            $query->where('floor_id', $request->floor_id);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $items = $query->paginate(20, ['*'], 'items_page')->withQueryString();

        $recentMutations = InventoryHistory::with(['inventoryItem', 'user'])
            ->where('change_type', 'mutation')
            ->latest()
            ->take(15)
            ->get();

        $buildings = CampusLocation::where('type', 'building')->orderBy('name')->get();
        $floors = CampusLocation::where('type', 'floor')->orderBy('name')->get();
        $categories = InventoryItem::getCategories();
        $assetTypes = InventoryItem::getAssetTypes();
        $statuses = InventoryItem::getStatusLookups();
        $conditions = InventoryItem::getConditionLookups();

        return view('pages.inventory.mutations.index', compact(
            'items',
            'recentMutations',
            'buildings',
            'floors',
            'categories',
            'assetTypes',
            'statuses',
            'conditions'
        ));
    }

    /**
     * Move location / update status / condition for selected items with history tracking.
     */
    public function mutate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['required', 'exists:inventory_items,id'],
            'new_building_id' => ['nullable', 'exists:campus_locations,id'],
            'new_floor_id' => ['nullable', 'exists:campus_locations,id'],
            'new_location_detail' => ['nullable', 'string', 'max:255'],
            'new_status' => ['nullable', Rule::enum(InventoryStatus::class)],
            'new_condition' => ['nullable', Rule::enum(InventoryCondition::class)],
            'mutation_notes' => ['required', 'string', 'max:1000'],
        ]);

        $userId = auth()->id();
        $updatedCount = 0;

        $items = InventoryItem::with(['building', 'floor', 'campusLocation'])
            ->whereIn('id', $validated['item_ids'])
            ->get();

        foreach ($items as $item) {
            $oldState = [
                'location' => $item->display_location,
                'status' => $item->status,
                'condition' => $item->condition,
            ];

            $updates = [];
            if (! empty($validated['new_building_id'])) {
                $updates['building_id'] = $validated['new_building_id'];
            }
            if (! empty($validated['new_floor_id'])) {
                $updates['floor_id'] = $validated['new_floor_id'];
            }
            if (isset($validated['new_location_detail']) && $validated['new_location_detail'] !== '') {
                $updates['location_detail'] = $validated['new_location_detail'];
            }
            if (! empty($validated['new_status'])) {
                $updates['status'] = $validated['new_status'];
            }
            if (! empty($validated['new_condition'])) {
                $updates['condition'] = $validated['new_condition'];
            }

            if (! empty($updates)) {
                $item->update($updates);
                $item->refresh();

                $item->recordHistory(
                    changeType: 'mutation',
                    oldState: $oldState,
                    notes: $validated['mutation_notes'],
                    userId: $userId
                );

                $updatedCount++;
            }
        }

        return back(fallback: route('inventory.mutations.index'))
            ->with('success', "Berhasil melakukan mutasi dan memperbarui {$updatedCount} aset beserta catatan riwayatnya.");
    }
}
