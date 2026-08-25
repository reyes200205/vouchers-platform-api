<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mismo patron que DatabaseUnavailableResponder / ExternalServiceUnavailableResponder,
 * para SpacesStorageException (fallo real al subir un archivo a DigitalOcean
 * Spaces -- ver SpacesStorageService::storeAndSign()). No aplica a
 * SpacesStorageService::temporaryUrlFor(), que a proposito atrapa el error y
 * regresa null en vez de relanzar (una URL firmada que no cargo no debe
 * tumbar toda la pantalla de revision).
 */
final class SpacesUnavailableResponder
{
    private const INTERNAL_CODE = 105040;

    private const CLIENT_MESSAGE = 'No pudimos guardar tu archivo. Intenta nuevamente más tarde. (Código: '.self::INTERNAL_CODE.')';

    public static function handle(SpacesStorageException $e): JsonResponse
    {
        // Detalle tecnico completo (mensaje real del SDK de S3/Spaces, causa
        // original, stack trace) solo al log en disco -- nunca al cliente.
        Log::error('DigitalOcean Spaces storage failed.', [
            'internal_code' => self::INTERNAL_CODE,
            'exception_class' => $e::class,
            'message' => $e->getMessage(),
            'previous_message' => $e->getPrevious()?->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        return response()->json([
            'code' => self::INTERNAL_CODE,
            'message' => self::CLIENT_MESSAGE,
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
