<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branches;

use App\Enums\BranchSettingsLogEventType;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Branches\UpdateBranchSettingRequest;
use App\Http\Resources\BranchSettingResource;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchSettingsLog;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

final class BranchSettingController extends ApiController
{
    public function show(Branch $branch): JsonResponse
    {
        $setting = BranchSetting::query()->firstOrCreate(['branch_id' => $branch->id]);

        return $this->success(new BranchSettingResource($setting));
    }

    public function update(UpdateBranchSettingRequest $request, Branch $branch, AuditLogger $audit): JsonResponse
    {
        $setting = DB::transaction(function () use ($request, $branch): BranchSetting {
            $setting = BranchSetting::query()->firstOrCreate(['branch_id' => $branch->id]);
            $before = $setting->only(array_keys($request->validated()));
            $setting->fill($request->validated());
            $setting->updated_by_user_id = $request->user()->id;
            $setting->save();

            BranchSettingsLog::query()->create([
                'branch_setting_id' => $setting->id,
                'branch_id' => $branch->id,
                'updated_by_user_id' => $request->user()->id,
                'event_type' => BranchSettingsLogEventType::SUCURSAL,
                'before_changes_json' => $before,
                'after_changes_json' => $setting->only(array_keys($request->validated())),
            ]);

            return $setting;
        });

        $audit->record($request, 'BRANCH_SETTINGS_UPDATED', 'branch-settings', 'Configuracion de sucursal actualizada.', $branch->id);

        return $this->success(new BranchSettingResource($setting));
    }
}