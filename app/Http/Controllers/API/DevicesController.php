<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer
 *
 * @subgroup Device
 *
 * @subgroupDescription APIs for managing Device
 */
class DevicesController extends Controller
{
    /**
     * Display devices of the auth company
     */
    public function index(): JsonResponse
    {
        $devices = auth()->guard('api')->user()
            ->company
            ->devices()
            ->orderBy('warranty_starting_at')
            ->get()->keyBy->id;

        return response()->json([
            'success' => true,
            'message' => 'Devices List Response',
            'data' => [
                'devices' => DeviceResource::collection($devices),
            ],
        ], 200);
    }
}
