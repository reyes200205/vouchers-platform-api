<?php

declare(strict_types=1);

namespace App\Http\Controllers\GeneralManager;

use App\Http\Controllers\ApiController;
use App\Http\Requests\PointSettings\UpdatePointSettingRequest;
use App\Http\Resources\PointSettingResource;
use App\Models\PointSetting;
use Illuminate\Http\JsonResponse;

final class PointSettingController extends ApiController
{
    public function show(): JsonResponse
    {
        $setting = PointSetting::query()->firstOrCreate();

        return $this->success(new PointSettingResource($setting));
    }

    public function update(UpdatePointSettingRequest $request): JsonResponse
    {
        $setting = PointSetting::query()->firstOrCreate();
        $setting->fill($request->validated());
        $setting->updated_by_user_id = $request->user()->id;
        $setting->save();

        return $this->success(new PointSettingResource($setting->refresh()));
    }
}