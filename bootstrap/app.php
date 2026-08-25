<?php

declare(strict_types=1);

use App\Exceptions\DatabaseUnavailableResponder;
use App\Exceptions\ExternalServiceUnavailableResponder;
use App\Exceptions\MailUnavailableResponder;
use App\Exceptions\SpacesStorageException;
use App\Exceptions\SpacesUnavailableResponder;
use App\Exceptions\UnexpectedErrorResponder;
use App\Http\Middleware\EnsureBusinessAbility;
use App\Http\Middleware\EnsureEmailVerified;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureVpnAccessForRoles;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\LogApiRequests;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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
        ], append: [
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'business.ability' => EnsureBusinessAbility::class,
            'force.json' => ForceJsonResponse::class,
            'log.api' => LogApiRequests::class,
            'user.active' => EnsureUserIsActive::class,
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

        // Evita que Laravel loguee esto una SEGUNDA vez con su formato
        // generico (report() corre independiente de render()): cuando es un
        // fallo de conexion, DatabaseUnavailableResponder::handle() ya lo
        // registra con su propio detalle tecnico completo -- ese debe ser el
        // unico registro de este incidente. Un error SQL normal (case B) no
        // coincide con isConnectionFailure() y sigue reportandose como
        // siempre.
        $exceptions->reportable(function (QueryException $e) {
            if (DatabaseUnavailableResponder::isConnectionFailure($e)) {
                return false;
            }
        });
        $exceptions->reportable(function (PDOException $e) {
            if (DatabaseUnavailableResponder::isConnectionFailure($e)) {
                return false;
            }
        });

        // Fallo al ESTABLECER la conexion a MySQL (host caido, connection
        // refused, timeout, red inalcanzable) -> respuesta generica 503 +
        // log tecnico completo. Un error SQL normal sobre una conexion que
        // SI se establecio (constraint, sintaxis, etc.) no coincide con la
        // deteccion de DatabaseUnavailableResponder::handle() y regresa
        // null, dejando que el manejo de excepciones normal de la app siga
        // aplicando sin cambios. Ver App\Exceptions\DatabaseUnavailableResponder.
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return DatabaseUnavailableResponder::handle($e);
        });

        // Red de seguridad: cubre un PDOException crudo que llegara sin
        // pasar por Illuminate\Database\Connection (que normalmente envuelve
        // todo en QueryException). En el flujo normal de la app esto no
        // deberia dispararse.
        $exceptions->render(function (PDOException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return DatabaseUnavailableResponder::handle($e);
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

        // Fallo al conectar con un servicio HTTP de terceros (hoy: Cloudflare
        // Turnstile en app/Rules/Turnstile.php) -> misma filosofia que la
        // conexion a MySQL: 503 generico + codigo propio + log tecnico
        // completo. Ver App\Exceptions\ExternalServiceUnavailableResponder.
        $exceptions->reportable(fn (ConnectionException $e) => false);
        $exceptions->render(function (ConnectionException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ExternalServiceUnavailableResponder::handle($e);
        });

        // Fallo real al guardar un archivo en DigitalOcean Spaces (ver
        // SpacesStorageService::storeAndSign()/assertConfigured()). No
        // aplica a temporaryUrlFor(), que a proposito absorbe el error y
        // regresa null para no tumbar una pantalla completa por una sola
        // URL firmada que no cargo.
        $exceptions->reportable(fn (SpacesStorageException $e) => false);
        $exceptions->render(function (SpacesStorageException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return SpacesUnavailableResponder::handle($e);
        });

        // Fallo al ENVIAR un correo (SMTP caido, timeout, credenciales
        // invalidas). Solo se dispara para envios que no estan ya envueltos
        // en su propio try/catch silencioso (ver
        // App\Exceptions\MailUnavailableResponder para el detalle de cuales).
        $exceptions->reportable(fn (TransportExceptionInterface $e) => false);
        $exceptions->render(function (TransportExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return MailUnavailableResponder::handle($e);
        });

        // Red de seguridad final: cualquier excepcion no anticipada que no
        // haya sido manejada por algo mas especifico arriba -- incluye un
        // error SQL genuino (QueryException que NO fue fallo de conexion,
        // ver DatabaseUnavailableResponder) y cualquier bug real. Debe ir
        // AL FINAL: Handler::render() prueba los render() en el orden en que
        // se registraron y usa el primero que regrese algo no nulo, asi que
        // todo lo especifico de arriba sigue teniendo prioridad. Ver
        // App\Exceptions\UnexpectedErrorResponder para la lista exacta de
        // exclusiones (validacion, autenticacion, HTTP con status propio).
        $exceptions->reportable(function (Throwable $e) {
            if (UnexpectedErrorResponder::isEligible($e)) {
                return false;
            }
        });
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return UnexpectedErrorResponder::handle($e);
        });
    })->create();
