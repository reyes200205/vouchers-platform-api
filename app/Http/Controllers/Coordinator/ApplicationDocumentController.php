<?php

declare(strict_types=1);

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\ApiController;
use App\Http\Requests\Applications\StoreApplicationDocumentRequest;
use App\Models\User;
use App\Services\Storage\SpacesStorageService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class ApplicationDocumentController extends ApiController
{
    public function store(StoreApplicationDocumentRequest $request, SpacesStorageService $storage): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasBusinessAbility('applications.create')) {
            return $this->forbidden();
        }

        $data = $request->validated();

        try {
            $upload = $storage->uploadApplicationDocument($request->file('document'), $data['type']);
        } catch (RuntimeException $exception) {
            return $this->error($exception->getMessage(), 503);
        }

        return $this->success([
            'type' => $data['type'],
            'path' => $upload['path'],
            'url' => $upload['temporary_url'],
        ]);
    }
}
