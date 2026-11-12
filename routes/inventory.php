<?php

use App\Http\Controllers\Inventory\BulkMaintenanceController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryMaintenanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [InventoryController::class, 'index'])->name('index');
    Route::post('/', [InventoryController::class, 'store'])->name('store');

    // Mutations (dedicated page & action)
    Route::get('/mutations', [InventoryController::class, 'mutationsIndex'])->name('mutations.index');
    Route::post('/mutate', [InventoryController::class, 'mutate'])->name('mutate');

    // Bulk Input Page & Store, Import Excel, Template (registered before /{item})
    Route::get('/bulk-input', [InventoryController::class, 'bulkCreate'])->name('bulk-input');
    Route::post('/bulk-store', [InventoryController::class, 'bulkStore'])->name('bulk-store');
    Route::get('/template', [InventoryController::class, 'downloadTemplate'])->name('template');
    Route::post('/import', [InventoryController::class, 'importExcel'])->name('import');

    // Single & Unified Maintenance
    Route::get('/maintenances', [InventoryMaintenanceController::class, 'index'])->name('maintenances.index');
    Route::post('/maintenances', [InventoryMaintenanceController::class, 'store'])->name('maintenances.store');
    Route::patch('/maintenances/{maintenance}', [InventoryMaintenanceController::class, 'update'])->name('maintenances.update');

    // Bulk Maintenance (actions & batch completion)
    Route::get('/bulk-maintenances', [BulkMaintenanceController::class, 'index'])->name('bulk.index');
    Route::get('/bulk-maintenances/create', [BulkMaintenanceController::class, 'create'])->name('bulk.create');
    Route::post('/bulk-maintenances', [BulkMaintenanceController::class, 'store'])->name('bulk.store');
    Route::get('/bulk-maintenances/{batch}', [BulkMaintenanceController::class, 'show'])->name('bulk.show');
    Route::patch('/bulk-maintenances/{batch}/complete', [BulkMaintenanceController::class, 'complete'])->name('bulk.complete');

    // Asset Detail & CRUD
    Route::get('/{item}', [InventoryController::class, 'show'])->name('show');
    Route::put('/{item}', [InventoryController::class, 'update'])->name('update');
    Route::delete('/{item}', [InventoryController::class, 'destroy'])->name('destroy');
});
