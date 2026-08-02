<?php

use App\Exceptions\BookingException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Secara bawaan Laravel mengarahkan tamu ke route bernama `login`.
        // Aplikasi ini murni API + satu halaman Blade, jadi route itu tidak ada
        // dan pemanggilannya akan melempar RouteNotFoundException (HTTP 500)
        // sebelum AuthenticationException sempat ditangani. Mengembalikan null
        // untuk request API membuat middleware melempar AuthenticationException
        // seperti seharusnya, sehingga renderer di bawah bisa membalas 401.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('api/*') ? null : '/'
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Kursi bentrok atau lock kedaluwarsa adalah hasil bisnis yang wajar,
        // bukan kegagalan sistem. Tanpa ini, satu load test 100 request
        // meninggalkan 99 baris `local.ERROR` yang menenggelamkan error asli.
        // Kliennya tetap menerima 409/410 seperti biasa.
        $exceptions->dontReport([
            BookingException::class,
        ]);

        // Balasan 401 yang konsisten untuk endpoint terproteksi, baik klien
        // mengirim header `Accept: application/json` maupun tidak.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Token tidak valid atau tidak disertakan.',
                    'error_code' => 'UNAUTHENTICATED',
                ], 401);
            }
        });
    })->create();
