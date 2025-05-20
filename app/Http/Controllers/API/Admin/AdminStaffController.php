<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAdminStaffRequest;
use App\Http\Requests\UpdateAdminStaffRequest;
use App\Http\Resources\AdminStaffResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 *
 * @subgroup Admin Staff
 *
 * @subgroupDescription APIs for managing Admin Staff
 */
class AdminStaffController extends Controller
{
    /**
     * Display a listing of admin staff.
     */
    public function index(): JsonResponse
    {
        $this->authorize('onlyAdminAndSuperAdmin', User::class);

        $staff = User::where('type', USER_TYPE_ADMIN_STAFF)->get();

        return response()->json([
            'success' => true,
            'message' => 'Staff List Response',
            'data' => [
                'staff' => AdminStaffResource::collection($staff),
            ],
        ]);
    }

    /**
     * Store a newly created admin staff user resource in storage.
     */
    public function store(StoreAdminStaffRequest $request): JsonResponse
    {
        $this->authorize('onlyAdminAndSuperAdmin', User::class);

        $data = $request->safe()->except(['password', 'is_active', 'cities']);
        $data += [
            'password' => bcrypt($request->password),
            'is_active' => boolval($request->is_active),
            'app_config' => '{"direction":"ltr", "allowEditablePrice": true}',
            'type' => USER_TYPE_ADMIN_STAFF,
        ];

        $staff = User::create($data);

        $staff->cities()->sync($request->cities);

        return response()->json([
            'success' => true,
            'message' => 'Admin Staff Added Successfully!',
            'data' => [
                'staff' => new AdminStaffResource($staff),
            ],
        ], 201);
    }

    /**
     * Show the specified admin staff.
     */
    public function show(User $staff): JsonResponse
    {
        $this->authorize('manageStaff', $staff);

        return response()->json([
            'success' => true,
            'message' => 'Admin Staff Resource',
            'data' => [
                'staff' => new AdminStaffResource($staff),
            ],
        ], 200);
    }

    /**
     * Update the specified admin staff.
     */
    public function update(UpdateAdminStaffRequest $request, User $user): JsonResponse
    {
        $this->authorize('manageStaff', $user);

        $user->update($request->validated());

        $user->cities()->sync($request->cities);

        return response()->json([
            'success' => true,
            'message' => 'Admin Staff Updated Successfully!',
            'data' => [
                'staff' => new AdminStaffResource($user),
            ],
        ], 200);
    }

    /**
     * Delete the specified admin staff.
     */
    public function destroy(User $staff): JsonResponse
    {
        $this->authorize('manageStaff', $staff);

        $staff->cities()->sync([]);
        $staff->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin Staff Deleted Successfully!',
            'data' => [],
        ], 200);
    }
}
