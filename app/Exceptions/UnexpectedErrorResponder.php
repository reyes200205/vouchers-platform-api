<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Red de seguridad final para CUALQUIER excepcion que llegue sin haber sido
 * ya manejada por algo mas especifico (DatabaseUnavailableResponder,
 * ExternalServiceUnavailableResponder, etc.) -- un bug real no anticipado,
 * un TypeError, lo que sea. Da al usuario un mensaje generico con codigo
 * propio y deja el detalle completo en el log, para que "me salio un error"
 * se pueda rastrear por codigo en vez de tener que reproducir el bug.
 *
 * Debe registrarse DESPUES de cualquier render() mas especifico en
 * bootstrap/app.php: Illuminate\Foundation\Exceptions\Handler::render()
 * recorre los callbacks en el orden en que se registraron y usa el primero
 * que regrese una respuesta no nula (ver renderViaCallbacks()), asi que un
 * handler especifico que regresa null (p.ej. un QueryException que NO es
 * fallo de conexion) sigue cayendo aqui -- a proposito: un error SQL
 * genuino tambien merece su propio codigo rastreable en el log.
 *
 * Excluye explicitamente lo que Laravel YA sabe renderizar bien por su
 * cuenta, para no pisar esas respuestas (errores de validacion con su
 * arreglo "errors", 401/403/404/419/429, etc.):
 * - Illuminate\Validation\ValidationException
 * - Illuminate\Auth\AuthenticationException
 * - Symfony\Component\HttpKernel\Exception\HttpExceptionInterface (cubre
 *   TODAS las excepciones HTTP con status propio -- NotFoundHttpException,
 *   AccessDeniedHttpException, TooManyRequestsHttpException, etc. Laravel
 *   convierte ModelNotFoundException/AuthorizationException/etc. a estas
 *   ANTES de llegar aqui, ver Handler::prepareException()).
 */
final class UnexpectedErrorResponder
{
    private const INTERNAL_CODE = 105099;

    private const CLIENT_MESSAGE = 'Ocurrió un error inesperado. Intenta nuevamente más tarde. (Código: '.self::INTERNAL_CODE.')';

    public static function isEligible(Throwable $e): bool
    {
        return ! $e instanceof HttpExceptionInterface
            && ! $e instanceof ValidationException
            && ! $e instanceof AuthenticationException;
    }

    public static function handle(Throwable $e): ?JsonResponse
    {
        if (! self::isEligible($e)) {
            return null;
        }

        Log::error('Unexpected error: unhandled exception reached the global fallback handler.', [
            'internal_code' => self::INTERNAL_CODE,
            'exception_class' => $e::class,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'code' => self::INTERNAL_CODE,
            'message' => self::CLIENT_MESSAGE,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
