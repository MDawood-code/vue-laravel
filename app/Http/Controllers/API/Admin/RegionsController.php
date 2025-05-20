<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRegionRequest;
use App\Http\Requests\UpdateRegionRequest;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
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
     * Store a newly created region in storage.
     */
    public function store(StoreRegionRequest $request): JsonResponse
    {
        $this->authorize('create', Region::class);

        $region = Region::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Region Added Successfully!',
            'data' => [
                'region' => new RegionResource($region),
            ],
        ], 201);
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

    /**
     * Update the specified region.
     */
    public function update(UpdateRegionRequest $request, Region $region): JsonResponse
    {
        $this->authorize('update', $region);

        $region->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Region Updated Successfully!',
            'data' => [
                'region' => new RegionResource($region),
            ],
        ], 201);
    }

    /**
     * Delete the specified region.
     */
    public function destroy(Region $region): JsonResponse
    {
        $this->authorize('delete', $region);

        $status = false;
        $message = '';
        $statusCode = 400;
        if ($region->cities()->count() == 0 && $region->companies()->count() == 0) {
            $region->delete();
            $status = true;
            $message = 'Region Deleted Successfully!';
            $statusCode = 201;
        } elseif ($region->cities()->count() > 0) {
            $status = false;
            $message = 'Region has associated cities. Cannot delete Region.';
            $statusCode = 400;
        } elseif ($region->companies()->count() > 0) {
            $status = false;
            $message = 'Region has associated companies. Cannot delete Region.';
            $statusCode = 400;
        }

        return response()->json([
            'success' => $status,
            'message' => $message,
            'data' => [],
        ], $statusCode);
    }
}
