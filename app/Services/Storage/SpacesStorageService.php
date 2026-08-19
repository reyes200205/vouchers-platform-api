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
        $this->assertConfigured();

        $extension = $file->extension() ?: 'bin';
        $path = 'testing/'.now()->format('Y/m/d').'/'.Str::uuid().'.'.$extension;

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

            $expiresAt = now()->addMinutes(10);

            return [
                'path' => $storedPath,
                'temporary_url' => Storage::disk('spaces')->temporaryUrl($storedPath, $expiresAt),
                'expires_at' => $expiresAt->toIso8601String(),
                'size' => $file->getSize() ?: 0,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
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