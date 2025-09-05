<?php

/**
 * TODO: Laravel 11 Kernel-less Application Bootstrap
 * 
 * Requirements from specification:
 * - Laravel 11 kernel-less structure
 * - Configure routing, middleware, and exceptions
 * - Support for web, API, and console routes
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
