<?php

use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Inventory\BulkMaintenanceController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryMaintenanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('index')->middleware('can:view-inventory');
    Route::post('/', [InventoryController::class, 'store'])->name('store')->middleware('can:create-inventory');

    // Mutations (dedicated page & action)
    Route::get('/mutations', [InventoryController::class, 'mutationsIndex'])->name('mutations.index')->middleware('can:manage-inventory');
    Route::post('/mutate', [InventoryController::class, 'mutate'])->name('mutate')->middleware('can:manage-inventory');

    // Bulk Input Page & Store, Import Excel, Template (registered before /{item})
    Route::get('/bulk-input', [InventoryController::class, 'bulkCreate'])->name('bulk-input')->middleware('can:create-inventory');
    Route::post('/bulk-store', [InventoryController::class, 'bulkStore'])->name('bulk-store')->middleware('can:create-inventory');
    Route::get('/template', [InventoryController::class, 'downloadTemplate'])->name('template')->middleware('can:create-inventory');
    Route::post('/import', [InventoryController::class, 'importExcel'])->name('import')->middleware('can:create-inventory');

    // Single & Unified Maintenance
    Route::get('/maintenances', [InventoryMaintenanceController::class, 'index'])->name('maintenances.index')->middleware('can:manage-maintenance');
    Route::post('/maintenances', [InventoryMaintenanceController::class, 'store'])->name('maintenances.store')->middleware('can:manage-maintenance');
    Route::patch('/maintenances/{maintenance}', [InventoryMaintenanceController::class, 'update'])->name('maintenances.update')->middleware('can:manage-maintenance');

    // Master Data & Kategori (Accessible from Inventory Module)
    Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data')->middleware('can:manage-inventory');

    // Bulk Maintenance (actions & batch completion)
    Route::get('/bulk-maintenances', [BulkMaintenanceController::class, 'index'])->name('bulk.index')->middleware('can:manage-maintenance');
    Route::get('/bulk-maintenances/create', [BulkMaintenanceController::class, 'create'])->name('bulk.create')->middleware('can:manage-maintenance');
    Route::post('/bulk-maintenances', [BulkMaintenanceController::class, 'store'])->name('bulk.store')->middleware('can:manage-maintenance');
    Route::get('/bulk-maintenances/{batch}', [BulkMaintenanceController::class, 'show'])->name('bulk.show')->middleware('can:manage-maintenance');
    Route::patch('/bulk-maintenances/{batch}/complete', [BulkMaintenanceController::class, 'complete'])->name('bulk.complete')->middleware('can:manage-maintenance');

    // Asset Detail & CRUD
    Route::get('/{item}', [InventoryController::class, 'show'])->name('show')->middleware('can:view-inventory');
    Route::put('/{item}', [InventoryController::class, 'update'])->name('update')->middleware('can:manage-inventory');
    Route::delete('/{item}', [InventoryController::class, 'destroy'])->name('destroy')->middleware('can:manage-inventory');
});
