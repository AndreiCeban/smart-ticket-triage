<?php

/**
 * TODO: API Routes
 * 
 * Requirements from specification:
 * - POST /tickets – create new ticket
 * - GET /tickets – list with filter, search, pagination params
 * - GET /tickets/{id} – get ticket detail
 * - PATCH /tickets/{id} – update status, category, note
 * - POST /tickets/{id}/classify – dispatch queued AI job
 * - GET /stats – JSON for dashboard charts
 */

use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

Route::apiResource('tickets', TicketController::class);
Route::post('tickets/{ticket}/classify', [TicketController::class, 'classify'])->name('tickets.classify');
Route::get('stats', [TicketController::class, 'stats'])->name('stats');
