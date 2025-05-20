<?php

namespace App\Http\Controllers\API\Admin\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityType\StoreActivityTypeRequest;
use App\Http\Requests\ActivityType\UpdateActivityTypeRequest;
use App\Http\Resources\ActivityTypeCollection;
use App\Http\Resources\ActivityTypeResource;
use App\Models\ActivityType;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin CRM
 *
 * @subgroup Activity Types
 *
 * @subgroupDescription APIs for managing Activity Types
 */
class ActivityTypeController extends Controller
{
    /**
     * Display a listing of the Activity Type.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', ActivityType::class);

        $activityTypes = ActivityType::all();

        return response()->json([
            'success' => true,
            'message' => 'Activity Types',
            'data' => new ActivityTypeCollection($activityTypes),
        ]);
    }

    /**
     * Store a newly created Activity Type resource in storage.
     */
    public function store(StoreActivityTypeRequest $request): JsonResponse
    {
        $this->authorize('create', ActivityType::class);

        $activityType = new ActivityType;
        $activityType->title = $request->title;
        $activityType->icon = $request->icon;
        $activityType->save();

        return response()->json([
            'success' => true,
            'message' => 'Activity Type created successfully.',
            'data' => [
                'activity_type' => new ActivityTypeResource($activityType),
            ],
        ]);
    }

    /**
     * Display the specified Activity Type resource.
     */
    public function show(ActivityType $activityType): JsonResponse
    {
        $this->authorize('view', $activityType);

        return response()->json([
            'success' => true,
            'message' => 'Activity Type Response.',
            'data' => [
                'activity_type' => new ActivityTypeResource($activityType),
            ],
        ], 201);
    }

    /**
     * Update the specified Activity Type resource in storage.
     */
    public function update(UpdateActivityTypeRequest $request, ActivityType $activityType): JsonResponse
    {
        $this->authorize('update', $activityType);

        $activityType->title = $request->title;
        $activityType->icon = $request->icon;
        $activityType->save();

        return response()->json([
            'success' => true,
            'message' => 'Activity Type Updated Successfully!',
            'data' => [
                'activity_type' => new ActivityTypeResource($activityType),
            ],
        ], 201);
    }
}
