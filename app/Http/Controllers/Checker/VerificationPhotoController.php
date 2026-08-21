<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\ApiController;
use App\Models\Application;
use App\Services\Storage\SpacesStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class VerificationPhotoController extends ApiController
{
    public function store(Request $request, Application $application, SpacesStorageService $storage): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:front_photo,id_with_person_photo'],
            'photo' => ['required', 'image', 'max:10240'],
        ]);

        try {
            $upload = $storage->uploadVerificationPhoto($request->file('photo'), $application->id, $data['type']);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 503);
        }

        return $this->success([
            'type' => $data['type'],
            'path' => $upload['path'],
            // URL firmada y temporal (bucket privado): solo sirve para la vista previa
            // inmediata en el modal de verificacion, no se persiste en la base de datos.
            'url' => $upload['temporary_url'],
        ]);
    }
}
