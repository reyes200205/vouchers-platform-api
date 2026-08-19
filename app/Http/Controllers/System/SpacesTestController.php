<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Storage\StoreSpacesTestUploadRequest;
use App\Services\Storage\SpacesStorageService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class SpacesTestController extends ApiController
{
    public function store(StoreSpacesTestUploadRequest $request, SpacesStorageService $storage): JsonResponse
    {
        try {
            $upload = $storage->uploadTestFile($request->file('file'));
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 503);
        }

        return $this->created($upload, 'Archivo de prueba guardado en DigitalOcean Spaces.');
    }
}