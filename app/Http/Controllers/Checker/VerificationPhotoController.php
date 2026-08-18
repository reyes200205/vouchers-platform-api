<?php

declare(strict_types=1);

namespace App\Http\Controllers\Checker;

use App\Http\Controllers\ApiController;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class VerificationPhotoController extends ApiController
{
    public function store(Request $request, Application $application): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:front_photo,id_with_person_photo,proof_of_address_photo'],
            'photo' => ['required', 'image', 'max:10240'],
        ]);

        $path = $request->file('photo')->store("verifications/{$application->id}", 'public');

        return $this->success([
            'type' => $data['type'],
            'path' => $path,
            // Se construye a partir del host de la peticion (no de APP_URL) para que
            // funcione igual en cualquier entorno local sin depender de ese valor.
            'url' => $request->getSchemeAndHttpHost() . '/storage/' . $path,
        ]);
    }
}
