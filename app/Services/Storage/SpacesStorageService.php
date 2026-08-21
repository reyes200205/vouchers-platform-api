<?php

declare(strict_types=1);

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
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

            return Storage::disk('spaces')->temporaryUrl($path, now()->addMinutes($expiresInMinutes));
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
            $storedPath = Storage::disk('spaces')->putFileAs(
                dirname($path),
                $file,
                basename($path),
                ['visibility' => 'private']
            );

            if ($storedPath === false) {
                throw new RuntimeException('DigitalOcean Spaces did not return an object path.');
            }

            $expiresAt = now()->addMinutes($expiresInMinutes);

            return [
                'path' => $storedPath,
                'temporary_url' => Storage::disk('spaces')->temporaryUrl($storedPath, $expiresAt),
                'expires_at' => $expiresAt->toIso8601String(),
            ];
        } catch (Throwable $exception) {
            report($exception);

            throw new RuntimeException('No se pudo guardar el archivo en DigitalOcean Spaces. Revisa la configuración y los permisos de la llave.', previous: $exception);
        }
    }

    private function assertConfigured(): void
    {
        foreach (['key', 'secret', 'region', 'bucket', 'endpoint'] as $key) {
            if (blank(config("filesystems.disks.spaces.{$key}"))) {
                throw new RuntimeException('DigitalOcean Spaces no está configurado. Revisa las variables DO_SPACES_* del entorno.');
            }
        }
    }
}
