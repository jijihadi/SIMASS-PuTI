<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CampusLocation;
use App\Models\InventoryCategory;
use App\Models\InventoryConditionLookup;
use App\Models\InventoryItem;
use App\Models\InventoryStatusLookup;
use App\Models\InventoryType;
use App\Models\ServiceCatalog;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    public function index(): View
    {
        $units = Unit::withCount('users')->orderBy('name')->get();
        $locations = CampusLocation::with(['parent', 'children'])->orderBy('type')->orderBy('name')->get();
        $services = ServiceCatalog::orderBy('category')->orderBy('name')->get();
        $inventoryCategories = InventoryCategory::withCount('items')->orderBy('id')->get();
        $inventoryTypes = InventoryType::withCount('items')->orderBy('id')->get();
        $inventoryStatuses = InventoryStatusLookup::withCount('items')->orderBy('id')->get();
        $inventoryConditions = InventoryConditionLookup::withCount('items')->orderBy('id')->get();

        return view('pages.admin.master-data.index', compact(
            'units',
            'locations',
            'services',
            'inventoryCategories',
            'inventoryTypes',
            'inventoryStatuses',
            'inventoryConditions'
        ));
    }

    // Units
    public function storeUnit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:units,code'],
        ]);

        $unit = Unit::create($validated);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'unit.created',
            'model_type' => Unit::class,
            'model_id' => $unit->id,
            'new_values' => $validated,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return redirect()->route('admin.master-data.index', ['tab' => 'units'])->with('success', "Unit {$unit->name} berhasil ditambahkan!");
    }

    public function updateUnit(Request $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('units', 'code')->ignore($unit->id)],
        ]);

        $unit->update($validated);

        return redirect()->route('admin.master-data.index', ['tab' => 'units'])->with('success', "Unit {$unit->name} berhasil diperbarui!");
    }

    public function destroyUnit(Unit $unit): RedirectResponse
    {
        $name = $unit->name;
        $unit->delete();

        return redirect()->route('admin.master-data.index', ['tab' => 'units'])->with('success', "Unit {$name} berhasil dihapus.");
    }

    // Locations
    public function storeLocation(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:campus_locations,id'],
            'type' => ['required', 'in:building,floor,room'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
        ]);

        $loc = CampusLocation::create($validated);

        return redirect()->route('admin.master-data.index', ['tab' => 'locations'])->with('success', "Lokasi {$loc->name} ({$loc->type}) berhasil ditambahkan!");
    }

    public function updateLocation(Request $request, CampusLocation $location): RedirectResponse
    {
        $validated = $request->validate([
            'parent_id' => ['nullable', 'exists:campus_locations,id'],
            'type' => ['required', 'in:building,floor,room'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
        ]);

        $location->update($validated);

        return redirect()->route('admin.master-data.index', ['tab' => 'locations'])->with('success', "Lokasi {$location->name} berhasil diperbarui!");
    }

    public function destroyLocation(CampusLocation $location): RedirectResponse
    {
        $name = $location->name;
        $location->delete();

        return redirect()->route('admin.master-data.index', ['tab' => 'locations'])->with('success', "Lokasi {$name} berhasil dihapus.");
    }

    // Service Catalog & SLA
    public function storeService(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'sla_resolution_hours' => ['required', 'integer', 'min:1'],
        ]);

        $service = ServiceCatalog::create($validated);

        return redirect()->route('admin.master-data.index', ['tab' => 'services'])->with('success', "Layanan {$service->name} (SLA {$service->sla_resolution_hours} jam) berhasil disimpan!");
    }

    public function updateService(Request $request, ServiceCatalog $service): RedirectResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'sla_resolution_hours' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $service->update($validated);

        return redirect()->route('admin.master-data.index', ['tab' => 'services'])->with('success', "Layanan {$service->name} berhasil diperbarui!");
    }

    public function destroyService(ServiceCatalog $service): RedirectResponse
    {
        $name = $service->name;
        $service->delete();

        return redirect()->route('admin.master-data.index', ['tab' => 'services'])->with('success', "Layanan {$name} berhasil dihapus.");
    }

    // Inventory Categories
    public function storeInventoryCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:inventory_categories,name'],
            'code' => ['nullable', 'string', 'max:50', 'unique:inventory_categories,code'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $cat = InventoryCategory::create($validated);

        return $this->redirectWithTab('inventory-categories', "Kategori inventaris {$cat->name} berhasil ditambahkan!");
    }

    public function updateInventoryCategory(Request $request, InventoryCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('inventory_categories', 'name')->ignore($category->id)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('inventory_categories', 'code')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $oldName = $category->name;
        $category->update($validated);

        // Cascade rename to items
        if ($oldName !== $category->name) {
            InventoryItem::where('category', $oldName)->update(['category' => $category->name]);
        }

        return $this->redirectWithTab('inventory-categories', "Kategori {$category->name} berhasil diperbarui!");
    }

    public function destroyInventoryCategory(InventoryCategory $category): RedirectResponse
    {
        $name = $category->name;
        $category->delete();

        return $this->redirectWithTab('inventory-categories', "Kategori inventaris {$name} berhasil dihapus.");
    }

    // Inventory Types
    public function storeInventoryType(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30', 'unique:inventory_types,code'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $type = InventoryType::create($validated);

        return $this->redirectWithTab('inventory-types', "Tipe inventaris {$type->name} berhasil ditambahkan!");
    }

    public function updateInventoryType(Request $request, InventoryType $type): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30', Rule::unique('inventory_types', 'code')->ignore($type->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $oldCode = $type->code;
        $type->update($validated);

        if ($oldCode !== $type->code) {
            InventoryItem::where('asset_type', $oldCode)->update(['asset_type' => $type->code]);
        }

        return $this->redirectWithTab('inventory-types', "Tipe inventaris {$type->name} berhasil diperbarui!");
    }

    public function destroyInventoryType(InventoryType $type): RedirectResponse
    {
        $name = $type->name;
        $type->delete();

        return $this->redirectWithTab('inventory-types', "Tipe inventaris {$name} berhasil dihapus.");
    }

    // Inventory Statuses
    public function storeInventoryStatus(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30', 'unique:inventory_statuses,code'],
            'color' => ['required', 'string', 'max:30'],
        ]);

        $status = InventoryStatusLookup::create($validated);

        return $this->redirectWithTab('inventory-statuses', "Status {$status->name} berhasil ditambahkan!");
    }

    public function updateInventoryStatus(Request $request, InventoryStatusLookup $status): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30', Rule::unique('inventory_statuses', 'code')->ignore($status->id)],
            'color' => ['required', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $oldCode = $status->code;
        $status->update($validated);

        if ($oldCode !== $status->code) {
            InventoryItem::where('status', $oldCode)->update(['status' => $status->code]);
        }

        return $this->redirectWithTab('inventory-statuses', "Status {$status->name} berhasil diperbarui!");
    }

    public function destroyInventoryStatus(InventoryStatusLookup $status): RedirectResponse
    {
        $name = $status->name;
        $status->delete();

        return $this->redirectWithTab('inventory-statuses', "Status {$name} berhasil dihapus.");
    }

    // Inventory Conditions
    public function storeInventoryCondition(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30', 'unique:inventory_conditions,code'],
            'color' => ['required', 'string', 'max:30'],
        ]);

        $condition = InventoryConditionLookup::create($validated);

        return $this->redirectWithTab('inventory-conditions', "Kondisi {$condition->name} berhasil ditambahkan!");
    }

    public function updateInventoryCondition(Request $request, InventoryConditionLookup $condition): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30', Rule::unique('inventory_conditions', 'code')->ignore($condition->id)],
            'color' => ['required', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $oldCode = $condition->code;
        $condition->update($validated);

        if ($oldCode !== $condition->code) {
            InventoryItem::where('condition', $oldCode)->update(['condition' => $condition->code]);
        }

        return $this->redirectWithTab('inventory-conditions', "Kondisi {$condition->name} berhasil diperbarui!");
    }

    public function destroyInventoryCondition(InventoryConditionLookup $condition): RedirectResponse
    {
        $name = $condition->name;
        $condition->delete();

        return $this->redirectWithTab('inventory-conditions', "Kondisi {$name} berhasil dihapus.");
    }

    private function redirectWithTab(string $tab, string $message): RedirectResponse
    {
        $route = str_contains(url()->previous(), 'inventory/master-data')
            ? 'inventory.master-data'
            : 'admin.master-data.index';

        return redirect()->route($route, ['tab' => $tab])->with('success', $message);
    }
}
