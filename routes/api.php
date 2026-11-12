<?php

use App\Http\Controllers\Api\V1\LunaTicketApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/luna')
    ->name('api.v1.luna.')
    ->middleware('luna.api')
    ->group(function () {
        Route::get('/catalog', [LunaTicketApiController::class, 'catalog'])->name('catalog');
        Route::get('/tickets', [LunaTicketApiController::class, 'index'])->name('tickets.index');
        Route::post('/tickets', [LunaTicketApiController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket_number}', [LunaTicketApiController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket_number}/reply', [LunaTicketApiController::class, 'reply'])->name('tickets.reply');
        Route::post('/tickets/{ticket_number}/cancel', [LunaTicketApiController::class, 'cancel'])->name('tickets.cancel');
    });
