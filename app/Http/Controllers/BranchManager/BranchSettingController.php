<?php

declare(strict_types=1);

namespace App\Http\Controllers\BranchManager;

use App\Enums\AuditEventType;
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
        $setting = BranchSetting::query()->firstOrCreate(['branch_id' => $branch->id])->refresh();

        return $this->success(new BranchSettingResource($setting));
    }

    public function update(UpdateBranchSettingRequest $request, Branch $branch, AuditLogger $audit): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('insurance_rates', $data)) {
            $data['insurance_rates_json'] = $data['insurance_rates'];
            unset($data['insurance_rates']);
        }

        $before = null;
        $after = null;

        $setting = DB::transaction(function () use ($request, $branch, $data, &$before, &$after): BranchSetting {
            $setting = BranchSetting::query()->firstOrCreate(['branch_id' => $branch->id])->refresh();
            $before = $setting->only(array_keys($data));
            $setting->fill($data);
            $setting->updated_by_user_id = $request->user()->id;
            $setting->save();
            $after = $setting->only(array_keys($data));

            BranchSettingsLog::query()->create([
                'branch_setting_id' => $setting->id,
                'branch_id' => $branch->id,
                'updated_by_user_id' => $request->user()->id,
                'event_type' => BranchSettingsLogEventType::SUCURSAL,
                'before_changes_json' => $before,
                'after_changes_json' => $after,
            ]);

            return $setting;
        });

        $audit->record(
            $request,
            AuditEventType::Updated,
            'branch-settings',
            'Configuracion de sucursal actualizada.',
            $branch->id,
            ['branch_setting_id' => $setting->id, 'changed_fields' => array_keys($data), 'after' => $after],
            null,
            $before
        );

        return $this->success(new BranchSettingResource($setting));
    }
}