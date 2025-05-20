<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCityRequest;
use App\Http\Requests\UpdateCityRequest;
use App\Http\Resources\CityResource;
use App\Models\City;
use App\Models\Region;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
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
     * Store a newly created city in storage.
     */
    public function store(StoreCityRequest $request, Region $region): JsonResponse
    {
        $this->authorize('create', City::class);

        $city = City::create($request->validated() + ['region_id' => $region->id]);

        return response()->json([
            'success' => true,
            'message' => 'City Added Successfully!',
            'data' => [
                'city' => new CityResource($city),
            ],
        ], 201);
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

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCityRequest $request, Region $region, City $city): JsonResponse
    {
        $this->authorize('update', $city);

        if ($city->belongsToRegion($region)) {
            $city->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'City Updated Successfully!',
                'data' => [
                    'city' => new CityResource($city),
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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Region $region, City $city): JsonResponse
    {
        $this->authorize('delete', $city);

        if ($city->belongsToRegion($region)) {
            $status = false;
            $message = '';
            $statusCode = 400;
            if ($city->users()->count() == 0 && $city->companies()->count() == 0) {
                $city->delete();
                $status = true;
                $message = 'City Deleted Successfully!';
                $statusCode = 201;
            } elseif ($city->users()->count() > 0) {
                $status = false;
                $message = 'City has associated users. Cannot delete City.';
                $statusCode = 400;
            } elseif ($city->companies()->count() > 0) {
                $status = false;
                $message = 'City has associated companies. Cannot delete City.';
                $statusCode = 400;
            }

            return response()->json([
                'success' => $status,
                'message' => $message,
                'data' => [],
            ], $statusCode);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Data Mismatch',
                'data' => [],
            ], 400);
        }
    }
}
