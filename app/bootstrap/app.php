<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Middleware\LogApiRequestMiddleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Square1\LaravelIdempotency\Http\Middleware\IdempotencyMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__ . '/../routes/console.php',
        using: function () {
            Route::domain('app.simplebank.local')
                ->middleware('web')
                ->group(base_path('routes/web.php'));

            Route::domain('api.simplebank.local')
                ->middleware('api', )
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'log.api' => LogApiRequestMiddleware::class,
            'idempotency' => IdempotencyMiddleware::class,
        ]);

        $middleware->appendToGroup('api', [
            'log.api',
            'idempotency',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
