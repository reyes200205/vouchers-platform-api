<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mismo patron que DatabaseUnavailableResponder, pero para llamadas HTTP
 * salientes a servicios de terceros (hoy: verificacion de Cloudflare
 * Turnstile en app/Rules/Turnstile.php). A diferencia de la conexion a
 * MySQL, aqui no hace falta distinguir "fallo de conexion" de "error normal"
 * -- Illuminate\Http\Client\ConnectionException YA es especificamente eso:
 * Laravel solo la lanza cuando no se pudo establecer/completar la conexion
 * HTTP (DNS, timeout, host inalcanzable). Un 4xx/5xx normal del servicio
 * externo no la dispara (esos se manejan aparte, como ya hace
 * Turnstile::validate() con $response->successful()).
 */
final class ExternalServiceUnavailableResponder
{
    private const INTERNAL_CODE = 105030;

    private const CLIENT_MESSAGE = 'No pudimos completar tu solicitud. Intenta nuevamente más tarde. (Código: '.self::INTERNAL_CODE.')';

    public static function handle(ConnectionException $e): JsonResponse
    {
        // Detalle tecnico completo (URL, host, mensaje real) solo al log en
        // disco -- nunca al cliente. La excepcion se marca no-reportable en
        // bootstrap/app.php para que este sea el unico registro.
        Log::error('External service connection failed.', [
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
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
