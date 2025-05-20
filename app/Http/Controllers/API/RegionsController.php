<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer
 *
 * @subgroup Region
 *
 * @subgroupDescription APIs for managing Region
 */
class RegionsController extends Controller
{
    /**
     * Display a listing of the regions.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Region::class);

        // TODO: Add country as Param when we have multiple Countries
        $regions = Region::all();

        return response()->json([
            'success' => true,
            'message' => 'Regions List Response',
            'data' => [
                'regions' => RegionResource::collection($regions),
            ],
        ]);
    }

    /**
     * Display the specified region.
     */
    public function show(Region $region): JsonResponse
    {
        $this->authorize('view', $region);

        return response()->json([
            'success' => true,
            'message' => 'Region Resource',
            'data' => [
                'region' => new RegionResource($region),
            ],
        ], 201);
    }
}
