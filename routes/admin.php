<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\MasterDataController;
use App\Http\Controllers\Admin\PortalModuleController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.dashboard');
    });

    Route::get('/dashboard', function () {
        return view('pages.admin.dashboard');
    })->name('dashboard');

    // User Management
    Route::post('/users/import-csv', [UserController::class, 'importCsv'])->name('users.import-csv');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
    Route::post('/users/{user}/reset-lockout', [UserController::class, 'resetLockout'])->name('users.reset-lockout');
    Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);

    // Portal Module Management
    Route::resource('modules', PortalModuleController::class)->except(['create', 'show', 'edit']);

    // Master Data & Locations
    Route::get('/master-data', [MasterDataController::class, 'index'])->name('master-data.index');
    Route::post('/units', [MasterDataController::class, 'storeUnit'])->name('units.store');
    Route::put('/units/{unit}', [MasterDataController::class, 'updateUnit'])->name('units.update');
    Route::delete('/units/{unit}', [MasterDataController::class, 'destroyUnit'])->name('units.destroy');

    Route::post('/locations', [MasterDataController::class, 'storeLocation'])->name('locations.store');
    Route::put('/locations/{location}', [MasterDataController::class, 'updateLocation'])->name('locations.update');
    Route::delete('/locations/{location}', [MasterDataController::class, 'destroyLocation'])->name('locations.destroy');

    Route::post('/services', [MasterDataController::class, 'storeService'])->name('services.store');
    Route::put('/services/{service}', [MasterDataController::class, 'updateService'])->name('services.update');
    Route::delete('/services/{service}', [MasterDataController::class, 'destroyService'])->name('services.destroy');

    Route::post('/inventory-categories', [MasterDataController::class, 'storeInventoryCategory'])->name('inventory-categories.store');
    Route::put('/inventory-categories/{category}', [MasterDataController::class, 'updateInventoryCategory'])->name('inventory-categories.update');
    Route::delete('/inventory-categories/{category}', [MasterDataController::class, 'destroyInventoryCategory'])->name('inventory-categories.destroy');

    Route::post('/inventory-types', [MasterDataController::class, 'storeInventoryType'])->name('inventory-types.store');
    Route::put('/inventory-types/{type}', [MasterDataController::class, 'updateInventoryType'])->name('inventory-types.update');
    Route::delete('/inventory-types/{type}', [MasterDataController::class, 'destroyInventoryType'])->name('inventory-types.destroy');

    Route::post('/inventory-statuses', [MasterDataController::class, 'storeInventoryStatus'])->name('inventory-statuses.store');
    Route::put('/inventory-statuses/{status}', [MasterDataController::class, 'updateInventoryStatus'])->name('inventory-statuses.update');
    Route::delete('/inventory-statuses/{status}', [MasterDataController::class, 'destroyInventoryStatus'])->name('inventory-statuses.destroy');

    Route::post('/inventory-conditions', [MasterDataController::class, 'storeInventoryCondition'])->name('inventory-conditions.store');
    Route::put('/inventory-conditions/{condition}', [MasterDataController::class, 'updateInventoryCondition'])->name('inventory-conditions.update');
    Route::delete('/inventory-conditions/{condition}', [MasterDataController::class, 'destroyInventoryCondition'])->name('inventory-conditions.destroy');

    // Audit Logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
