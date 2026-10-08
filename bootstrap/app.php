<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        then: function (): void {
            /*
             * Rute API memakai middleware `web` (bukan grup `api`) karena autentikasi
             * memakai session cookie di domain yang sama, dan `PreventRequestForgery`
             * hanya ada di grup tersebut — tanpa itu seluruh POST dari SPA akan
             * ditolak sebagai upaya CSRF.
             */
            Route::middleware('web')->prefix('api')->group(base_path('routes/api.php'));
        },
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureRole::class,
        ]);

        $middleware->appendToGroup('web', EnsureAccountIsActive::class);

        /*
         * Webhook pembayaran datang dari server Midtrans tanpa sesi browser,
         * jadi tidak bisa membawa token CSRF. Keasliannya diverifikasi lewat
         * tanda tangan Midtrans di PaymentController.
         */
        $middleware->validateCsrfTokens(except: [
            'api/payments/midtrans/notification',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
