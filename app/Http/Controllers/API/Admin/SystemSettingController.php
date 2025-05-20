<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemSettingRequest;
use App\Http\Resources\SystemSettingResource;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 *
 * @subgroup SystemSetting
 *
 * @subgroupDescription APIs for managing SystemSetting
 */
class SystemSettingController extends Controller
{
    public function getLoggedInUser(): ?User
    {
        return auth()->guard('api')->user();
    }

    /**
     * Display the specified resource.
     */
    public function show(): JsonResponse
    {
        $this->authorize('view', SystemSetting::class);

        $systemSetting = SystemSetting::first();

        return response()->json([
            'success' => true,
            'message' => 'System Settings Response.',
            'data' => [
                'system_settings' => $systemSetting ? new SystemSettingResource($systemSetting) : [],
            ],
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSystemSettingRequest $request): JsonResponse
    {
        $this->authorize('update', SystemSetting::class);

        $systemSetting = SystemSetting::updateOrCreate(
            [],
            $request->validated(),
        );

        return response()->json([
            'success' => true,
            'message' => 'System Setting Updated Successfully!',
            'data' => [
                'system_settings' => new SystemSettingResource($systemSetting),
            ],
        ], 201);
    }
}
