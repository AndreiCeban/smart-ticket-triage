<?php

/**
 * TODO: Web Routes
 * 
 * Requirements from specification:
 * - Single Build: Vite bundles the SPA served from /public
 * - Fallback route for Vue SPA routing
 * - Serve Vue SPA for all frontend routes
 */

use Illuminate\Support\Facades\Route;

// Serve the Vue SPA for all frontend routes
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '.*');
