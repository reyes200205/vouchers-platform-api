<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Exceptions\SpacesStorageException;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class SpacesStorageService
{
    /**
     * @return array{path: string, temporary_url: string, expires_at: string, size: int, mime_type: string}
     */
    public function uploadTestFile(UploadedFile $file): array
    {
        $path = 'testing/'.now()->format('Y/m/d').'/'.Str::uuid().'.'.($file->extension() ?: 'bin');

        return [
            ...$this->storeAndSign($file, $path, expiresInMinutes: 10),
            'size' => $file->getSize() ?: 0,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
        ];
    }

    /**
     * Guarda una fotografia de evidencia de una visita de verificacion (fachada, INE con
     * la persona o comprobante de domicilio) en el bucket privado de Spaces, agrupada por
     * solicitud y tipo para poder auditar cada visita.
     *
     * @return array{path: string, temporary_url: string, expires_at: string}
     */
    public function uploadVerificationPhoto(UploadedFile $file, int $applicationId, string $type): array
    {
        $path = "verifications/{$applicationId}/{$type}/".Str::uuid().'.'.($file->extension() ?: 'jpg');

        return $this->storeAndSign($file, $path, expiresInMinutes: 30);
    }

    /**
     * Guarda un documento (INE frente/reverso, comprobante de domicilio) que el
     * coordinador captura al dar de alta una solicitud. Se sube antes de que exista
     * el ID de la Application, por eso se agrupa solo por tipo con un UUID propio;
     * el path resultante se manda de vuelta en el alta y ahi queda ligado al folio real.
     *
     * @return array{path: string, temporary_url: string, expires_at: string}
     */
    public function uploadApplicationDocument(UploadedFile $file, string $type): array
    {
        $path = "applications/pending/{$type}/".Str::uuid().'.'.($file->extension() ?: 'jpg');

        return $this->storeAndSign($file, $path, expiresInMinutes: 30);
    }

    /**
     * Firma una URL temporal para un objeto ya guardado en Spaces (por ejemplo,
     * la ruta de una foto de verificación persistida en ApplicationVerification).
     * Usar al leer/mostrar evidencia, no al subirla.
     *
     * A diferencia de storeAndSign, aquí NO se relanza la excepción: esto se usa
     * al armar el detalle de una solicitud junto con otras fotos, y una llave de
     * Spaces vencida o mal configurada no debe tumbar toda la pantalla de
     * revisión de la Bandeja de Aprobaciones, solo esa URL en particular.
     */
    public function temporaryUrlFor(?string $path, int $expiresInMinutes = 30): ?string
    {
        if (blank($path)) {
            return null;
        }

        try {
            $this->assertConfigured();

            return $this->signUrl($path, now()->addMinutes($expiresInMinutes));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @return array{path: string, temporary_url: string, expires_at: string}
     */
    private function storeAndSign(UploadedFile $file, string $path, int $expiresInMinutes): array
    {
        $this->assertConfigured();

        try {
            // Un solo error de red no debe tirar la subida: se reintenta hasta
            // 3 veces (con una espera chica y creciente entre cada intento)
            // antes de darse por vencido. UploadedFile envuelve un archivo
            // temporal real en disco, no un stream que se consuma al leerlo,
            // asi que reintentar putFileAs vuelve a leerlo desde el inicio
            // cada vez -- es seguro repetirlo.
            $storedPath = retry(3, function () use ($file, $path) {
                $result = Storage::disk('spaces')->putFileAs(
                    dirname($path),
                    $file,
                    basename($path),
                    ['visibility' => 'private']
                );

                if ($result === false) {
                    throw new SpacesStorageException('DigitalOcean Spaces did not return an object path.');
                }

                return $result;
            }, fn (int $attempt): int => $attempt * 200);

            $expiresAt = now()->addMinutes($expiresInMinutes);

            return [
                'path' => $storedPath,
                'temporary_url' => $this->signUrl($storedPath, $expiresAt),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        } catch (Throwable $exception) {
            // No se reporta aqui con report(): SpacesUnavailableResponder
            // (bootstrap/app.php) ya loguea el detalle completo -- incluida
            // esta causa original via getPrevious() -- cuando la excepcion
            // llegue al manejador global. Reportar tambien aqui duplicaria
            // el mismo incidente en el log con dos formatos distintos.
            throw new SpacesStorageException('No se pudo guardar el archivo en DigitalOcean Spaces. Revisa la configuración y los permisos de la llave.', previous: $exception);
        }
    }

    /**
     * Firma la URL con hasta 3 intentos -- generar una URL firmada no debe
     * fallar por un tropiezo pasajero de red al armar la peticion firmada.
     */
    private function signUrl(string $path, DateTimeInterface $expiresAt): string
    {
        return retry(3, fn () => Storage::disk('spaces')->temporaryUrl($path, $expiresAt), fn (int $attempt): int => $attempt * 100);
    }

    private function assertConfigured(): void
    {
        foreach (['key', 'secret', 'region', 'bucket', 'endpoint'] as $key) {
            if (blank(config("filesystems.disks.spaces.{$key}"))) {
                throw new SpacesStorageException('DigitalOcean Spaces no está configurado. Revisa las variables DO_SPACES_* del entorno.');
            }
        }
    }
}
