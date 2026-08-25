<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Contracts\Database\LostConnectionDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Punto centralizado (llamado desde bootstrap/app.php) para diferenciar un
 * fallo al ESTABLECER la conexion a MySQL (host caido, connection refused,
 * timeout, red inalcanzable) de un error SQL normal ocurrido sobre una
 * conexion que si se establecio (constraint, sintaxis, etc.). Solo lo
 * primero debe convertirse en la respuesta 503 generica; lo segundo debe
 * seguir el manejo de excepciones normal de la aplicacion.
 *
 * La deteccion no reinventa una lista de mensajes de PDO/MySQL: reutiliza
 * Illuminate\Database\LostConnectionDetector, el mismo mecanismo que Laravel
 * usa internamente para decidir si reintenta una query con una conexion
 * fresca (ver Illuminate\Database\Connectors\Connector::createConnection()).
 * Esa lista ya cubre "Connection refused", "Connection timed out", DNS/red
 * inalcanzable, "server has gone away", etc. -- exactamente los casos
 * pedidos, mantenidos por el propio framework en vez de por nosotros.
 */
final class DatabaseUnavailableResponder
{
    /**
     * Codigo interno (no HTTP) que identifica este tipo de falla para
     * quienes consumen la API. El cliente NO debe recibir ningun otro
     * detalle tecnico mas alla de este numero y el mensaje generico.
     */
    private const INTERNAL_CODE = 105020;

    /**
     * Mensaje generico para el cliente, con el codigo ya incrustado. Se
     * genera aqui (no en el frontend) para que cualquier consumidor de la
     * API -- el SPA de Nuxt, un futuro cliente movil, etc. -- solo tenga que
     * mostrar "message" tal cual, sin lógica extra para pegarle el codigo.
     */
    private const CLIENT_MESSAGE = 'Servicio no disponible. Intenta nuevamente más tarde. (Código: '.self::INTERNAL_CODE.')';

    /**
     * Codigos de error del cliente MySQL (mysqlnd/libmysqlclient) que
     * significan especificamente "no se pudo ESTABLECER la conexion":
     * 2002 connection refused/timed out por socket, 2003 no se pudo
     * conectar al host, 2005 host desconocido, 2006/2013 el servidor se
     * fue justo al conectar. SQLSTATE[HY000] [NNNN] es el formato fijo que
     * usa el driver mysqlnd de PHP -- ese prefijo NO cambia con el idioma
     * del sistema operativo, solo la descripcion que le sigue.
     */
    private const CONNECTION_ERROR_CODES = '2002|2003|2005|2006|2013';

    /**
     * Cuando Laravel envuelve el error en QueryException, el mensaje de PDO
     * original vive en getPrevious(), no en el mensaje de la propia
     * QueryException. Si no hay previous (p.ej. un PDOException crudo llega
     * directo), se evalua la excepcion misma.
     *
     * Se combinan dos detecciones:
     * 1. Illuminate\Database\LostConnectionDetector (la lista de mensajes
     *    que Laravel ya mantiene) -- cubre muchos casos ("server has gone
     *    away", DNS, SSL, etc.) pero busca texto en INGLES.
     * 2. Un regex sobre el codigo de error de MySQL (ver
     *    CONNECTION_ERROR_CODES), independiente del idioma.
     *
     * La combinacion es necesaria: se confirmo en pruebas reales (conexion
     * rechazada de verdad, no simulada) que en un sistema operativo con
     * locale en espanol, PDO regresa el mensaje traducido -- "No se puede
     * establecer una conexion ya que el equipo de destino denego
     * expresamente dicha conexion" en vez de "Connection refused" -- y solo
     * el regex por codigo lo detecta ahi. Sin este fallback, un servidor de
     * produccion con locale distinto al ingles dejaria pasar el error de
     * conexion como si fuera un error SQL normal.
     */
    public static function isConnectionFailure(Throwable $e): bool
    {
        $original = $e->getPrevious() ?? $e;

        if (app(LostConnectionDetector::class)->causedByLostConnection($original)) {
            return true;
        }

        return (bool) preg_match(
            '/SQLSTATE\[HY000\]\s*\[('.self::CONNECTION_ERROR_CODES.')\]/',
            $original->getMessage()
        );
    }

    /**
     * @return JsonResponse|null  La respuesta 503 generica si $e fue causada
     *                            por un fallo de conexion; null si no lo fue,
     *                            para que el llamador deje que Laravel siga
     *                            con su manejo de excepciones normal.
     */
    public static function handle(Throwable $e): ?JsonResponse
    {
        if (! self::isConnectionFailure($e)) {
            return null;
        }

        $original = $e->getPrevious() ?? $e;

        // El detalle tecnico completo (mensaje real de PDO, SQLSTATE, host,
        // stack trace) SOLO va aqui -- al log de Laravel en disco (ver
        // config/logging.php), nunca al cliente. No depende de MySQL: los
        // canales configurados (single/daily/stack) escriben a
        // storage/logs/laravel.log, asi que siguen funcionando aunque la
        // base de datos este completamente caida. La excepcion se marca
        // como no-reportable en bootstrap/app.php para que Laravel no la
        // vuelva a loguear por su cuenta con su formato generico -- este es
        // el unico registro de este incidente.
        Log::error('Database connection failed: unable to establish a connection to MySQL.', [
            'internal_code' => self::INTERNAL_CODE,
            'exception_class' => $original::class,
            'message' => $original->getMessage(),
            'file' => $original->getFile(),
            'line' => $original->getLine(),
            'trace' => $original->getTraceAsString(),
        ]);

        return response()->json([
            'code' => self::INTERNAL_CODE,
            'message' => self::CLIENT_MESSAGE,
        ], Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
