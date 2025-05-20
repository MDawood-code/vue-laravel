<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CityResource;
use App\Models\City;
use App\Models\Region;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer
 *
 * @subgroup City
 *
 * @subgroupDescription APIs for managing City
 */
class CitiesController extends Controller
{
    /**
     * Display a listing of the cities.
     */
    public function index(Region $region): JsonResponse
    {
        $this->authorize('viewAny', City::class);

        $cities = $region->cities()->get();

        return response()->json([
            'success' => true,
            'message' => 'Cities List Response',
            'data' => [
                'cities' => CityResource::collection($cities),
            ],
        ]);
    }

    /**
     * Display the specified city.
     */
    public function show(Region $region, City $city): JsonResponse
    {
        $this->authorize('view', $city);

        if ($city->belongsToRegion($region)) {
            return response()->json([
                'success' => true,
                'message' => 'Region Resource',
                'data' => [
                    'region' => new CityResource($city),
                ],
            ], 201);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Data Mismatch',
                'data' => [],
            ], 400);
        }
    }
}
