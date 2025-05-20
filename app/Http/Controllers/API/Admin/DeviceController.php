<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRequest\StoreDeviceRequest;
use App\Http\Requests\DeviceRequest\UpdateDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Company;
use App\Models\CrmLog;
use App\Models\Device;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 *
 * @subgroup Device
 *
 * @subgroupDescription APIs for managing Device
 */
class DeviceController extends Controller
{
    /**
     * Display devices of the given company
     */
    public function index(Company $company): JsonResponse
    {
        $this->authorize('adminViewAny', Device::class);

        $devices = $company->devices()
            ->orderBy('model')
            ->get()->keyBy->id;

        return response()->json([
            'success' => true,
            'message' => 'Devices List Response',
            'data' => [
                'devices' => DeviceResource::collection($devices),
            ],
        ], 200);
    }

    /**
     * Store a newly created device in storage.
     */
    public function store(StoreDeviceRequest $request, Company $company): JsonResponse
    {
        $this->authorize('create', Device::class);

        $device = $company->devices()->create($request->validated());

        $company->crmLogs()->create([
            'created_by' => auth()->id(),
            'action' => 'added a device',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device Added Successfully!',
            'data' => [
                'device' => new DeviceResource($device),
            ],
        ], 201);
    }

    /**
     * Display the specified device.
     */
    public function show(Device $device): JsonResponse
    {
        $this->authorize('adminView', $device);

        return response()->json([
            'success' => true,
            'message' => 'Device Response.',
            'data' => [
                'device' => new DeviceResource($device),
            ],
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDeviceRequest $request, Device $device): JsonResponse
    {
        $this->authorize('update', $device);

        $device->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Device Updated Successfully!',
            'data' => [
                'device' => new DeviceResource($device),
            ],
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Device $device): JsonResponse
    {
        $this->authorize('delete', $device);

        $company_id = $device->company_id;
        $device->delete();

        CrmLog::create([
            'created_by' => auth()->id(),
            'company_id' => $company_id,
            'action' => 'deleted a device',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device has been deleted.',
            'data' => [],
        ], 200);
    }
}
