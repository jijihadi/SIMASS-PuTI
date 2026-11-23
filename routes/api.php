<?php

use App\Http\Controllers\Api\V1\LunaKnowledgeBaseController;
use App\Http\Controllers\Api\V1\LunaTicketApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/luna')
    ->name('api.v1.luna.')
    ->middleware(['throttle:60,1', 'luna.api'])
    ->group(function () {
        // Catalog & Live Master Data
        Route::get('/catalog', [LunaTicketApiController::class, 'catalog'])->name('catalog');

        // Tickets (WhatsApp / AI Integration)
        Route::get('/tickets', [LunaTicketApiController::class, 'index'])->name('tickets.index');
        Route::post('/tickets', [LunaTicketApiController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/{ticket_number}', [LunaTicketApiController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket_number}/reply', [LunaTicketApiController::class, 'reply'])->name('tickets.reply');
        Route::post('/tickets/{ticket_number}/cancel', [LunaTicketApiController::class, 'cancel'])->name('tickets.cancel');

        // Knowledge Base (Sync & CRUD for External AI Repo)
        Route::get('/knowledge-base', [LunaKnowledgeBaseController::class, 'index'])->name('kb.index');
        Route::post('/knowledge-base', [LunaKnowledgeBaseController::class, 'store'])->name('kb.store');
        Route::get('/knowledge-base/{document}', [LunaKnowledgeBaseController::class, 'show'])->name('kb.show');
        Route::put('/knowledge-base/{document}', [LunaKnowledgeBaseController::class, 'update'])->name('kb.update');
        Route::delete('/knowledge-base/{document}', [LunaKnowledgeBaseController::class, 'destroy'])->name('kb.destroy');
    });
