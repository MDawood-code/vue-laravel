<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequest\StoreAdminRequest;
use App\Http\Requests\AdminRequest\UpdateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 *
 * @subgroup Admin
 *
 * @subgroupDescription APIs for managing Admin
 */
class AdminController extends Controller
{
    /**
     * Display a listing of admins.
     */
    public function index(): JsonResponse
    {
        $this->authorize('onlySuperAdmin', User::class);

        $admins = User::where('type', USER_TYPE_ADMIN)->get();

        return response()->json([
            'success' => true,
            'message' => 'Admin List Response',
            'data' => [
                'admins' => AdminResource::collection($admins),
            ],
        ]);
    }

    /**
     * Store a newly created admin user resource in storage.
     */
    public function store(StoreAdminRequest $request): JsonResponse
    {
        $this->authorize('onlySuperAdmin', User::class);

        $data = $request->safe()->except(['password', 'is_active']);
        $data += [
            'password' => bcrypt($request->password),
            'is_active' => boolval($request->is_active),
            'app_config' => '{"direction":"ltr", "allowEditablePrice": true}',
            'type' => USER_TYPE_ADMIN,
            'can_add_edit_product' => true,
            'can_add_edit_customer' => true,
            'can_add_pay_sales_invoice' => true,
            'can_view_sales_invoice' => true,
            'can_request_stock_transfer' => true,
            'can_approve_stock_transfer' => true,
            'is_machine_user' => true,
        ];

        $admin = User::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Admin Added Successfully!',
            'data' => [
                'admin' => new AdminResource($admin),
            ],
        ], 201);
    }

    /**
     * Show the specified admin.
     */
    public function show(User $admin): JsonResponse
    {
        $this->authorize('manageAdmin', $admin);

        return response()->json([
            'success' => true,
            'message' => 'Admin Resource',
            'data' => [
                'admin' => new AdminResource($admin),
            ],
        ], 201);
    }

    /**
     * Update the specified admin user.
     */
    public function update(UpdateAdminRequest $request, User $user): JsonResponse
    {
        $this->authorize('manageAdmin', $user);

        $user->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Admin Updated Successfully!',
            'data' => [
                'admin' => new AdminResource($user),
            ],
        ], 201);
    }

    /**
     * Delete the specified admin user.
     */
    public function destroy(User $admin): JsonResponse
    {
        $this->authorize('manageAdmin', $admin);

        $admin->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin Deleted Successfully!',
            'data' => [],
        ], 201);
    }
}
