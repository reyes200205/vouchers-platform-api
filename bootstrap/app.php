<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureBusinessAbility;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureVpnAccessForRoles;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequests;
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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'business.ability' => EnsureBusinessAbility::class,
            'force.json' => ForceJsonResponse::class,
            'log.api' => LogApiRequests::class,
            'verified' => EnsureEmailVerified::class,
            'vpn.restrict' => EnsureVpnAccessForRoles::class,
        ]);

        // Solo las cabeceras se fijan aqui (no dependen de config()). La
        // lista de proxies confiables (TRUSTED_PROXIES) se aplica en
        // AppServiceProvider::boot() en vez de aqui: esta closure corre antes
        // de que 'config' este registrado en el contenedor, y ademas un
        // env() suelto fuera de un archivo de config regresa null cuando hay
        // config cacheado en produccion.
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            if ($request->is('api/*')) {
                return true;
            }

            return $request->expectsJson();
        });

        $exceptions->render(function (Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                $previous = $e->getPrevious();

                if ($previous instanceof Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $modelName = class_basename($previous->getModel());

                    return response()->json([
                        'success' => false,
                        'message' => "{$modelName} not found",
                    ], 404);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint not found',
                ], 404);
            }
        });
    })->create();
