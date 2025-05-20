<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessTypeVerificationCollection;
use App\Http\Resources\BusinessTypeVerificationResource;
use App\Models\BusinessTypeVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @group Admin
 *
 * @subgroup Business Type Verification
 *
 * @subgroupDescription APIs for managing Business Type Verification
 */
class BusinessTypeVerificationController extends Controller
{
    /**
     * Get all business type verifications
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', BusinessTypeVerification::class);

        $business_type_verifications = BusinessTypeVerification::all();

        return response()->json([
            'success' => true,
            'message' => 'Issue Types',
            'data' => new BusinessTypeVerificationCollection($business_type_verifications),
        ]);
    }

    /**
     * Store a newly created BusinessTypeVerification in storage.
     *
     * @bodyParam type string required The type of the BusinessTypeVerification. Example: 'Type A'.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', BusinessTypeVerification::class);

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|unique:business_type_verifications',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $business_type_verification = new BusinessTypeVerification;
        $business_type_verification->type = $request->type;
        $business_type_verification->save();

        return response()->json([
            'success' => true,
            'message' => 'Business Type Verification created successfully.',
            'data' => new BusinessTypeVerificationResource($business_type_verification),
        ]);
    }

    /**
     * Update a BusinessTypeVerification in storage.
     *
     * @bodyParam type string required The type of the BusinessTypeVerification. Example: 'Type A'.
     */
    public function update(Request $request, BusinessTypeVerification $business_type_verification): JsonResponse
    {
        $this->authorize('update', $business_type_verification);

        $validator = Validator::make($request->all(), [
            'type' => 'required|string|unique:business_type_verifications,type,'.$business_type_verification->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $business_type_verification->type = $request->type;
        $business_type_verification->save();

        return response()->json([
            'success' => true,
            'message' => 'Business Type Verification Updated Successfully!',
            'data' => new BusinessTypeVerificationResource($business_type_verification),
        ], 201);
    }

    /**
     * Delete a BusinessTypeVerification from storage.
     */
    public function destroy(BusinessTypeVerification $business_type_verification): JsonResponse
    {
        $this->authorize('delete', $business_type_verification);

        if ($business_type_verification->companies()->where('status', COMPANY_STATUS_ACTIVE)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Associated active companies found. First change their verification type.',
                'data' => [],
            ], 400);
        }

        $business_type_verification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Business Type Verification has been deleted.',
            'data' => [],
        ], 200);
    }
}
