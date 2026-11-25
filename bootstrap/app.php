<?php

use App\Http\Middleware\AdminAccessMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Middleware\VerifyLunaApiKey;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectTo(
            guests: '/',
            users: '/portal'
        );
        $middleware->alias([
            'admin' => AdminAccessMiddleware::class,
            'luna.api' => VerifyLunaApiKey::class,
        ]);
        $middleware->web(append: [
            SecurityHeadersMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/v1/luna/*')) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status);
            }

            if ($e instanceof LockTimeoutException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permintaan dengan Idempotency-Key yang sama sedang diproses. Silakan coba lagi.',
                ], 409);
            }

            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();

                return response()->json([
                    'success' => false,
                    'message' => match ($status) {
                        401 => 'Unauthorized.',
                        403 => 'Forbidden.',
                        404 => 'Resource tidak ditemukan.',
                        405 => 'Metode HTTP tidak diizinkan.',
                        429 => 'Terlalu banyak permintaan.',
                        default => $e->getMessage() ?: 'Permintaan tidak dapat diproses.',
                    },
                ], $status, $e->getHeaders());
            }

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server SIMASS.',
            ], 500);
        });
    })->create();
