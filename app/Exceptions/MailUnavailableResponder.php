<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Mismo patron que los demas responders de servicios externos, para fallos
 * de ENVIO de correo (SMTP caido, timeout, credenciales invalidas, etc.).
 * Symfony Mailer (lo que Laravel usa desde la version 9) lanza cualquier
 * implementacion de TransportExceptionInterface especificamente para fallos
 * de transporte -- no para plantillas mal armadas ni otros bugs, asi que no
 * hace falta distinguir casos como si con la conexion a MySQL.
 *
 * Solo aplica a llamadas a Mail::send() que NO estan ya envueltas en su
 * propio try/catch silencioso -- ver OneTimePasswordService,
 * RequestVoucherService::sendIssuedMail() y
 * DecideApplicationService::sendApprovedMail(), que a proposito absorben el
 * error para no tumbar un flujo que ya tuvo exito (login, vale aprobado,
 * distribuidora aprobada) solo porque el correo de aviso fallo. El unico
 * envio que SI puede tumbar su propia solicitud es
 * PasswordResetService::sendResetLink(), donde el correo es la entrega
 * completa de la accion.
 */
final class MailUnavailableResponder
{
    private const INTERNAL_CODE = 105050;

    private const CLIENT_MESSAGE = 'No pudimos enviar el correo. Intenta nuevamente más tarde. (Código: '.self::INTERNAL_CODE.')';

    public static function handle(TransportExceptionInterface $e): JsonResponse
    {
        Log::error('Mail transport failed: unable to send email.', [
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
