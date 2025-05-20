<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomFeatureResource;
use App\Models\CustomFeature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin
 *
 * @subgroup CustomFeature
 *
 * @subgroupDescription APIs for managing CustomFeature
 */
class CustomFeatureController extends Controller
{
    /**
     * Update the specified resource in storage.
     *
     * @bodyParam status bool required The status of the CustomFeature. Example: 1.
     */
    public function update(Request $request, CustomFeature $customFeature): JsonResponse
    {
        $this->authorize('update', $customFeature);

        $customFeature->status = (bool) $request->status;
        $customFeature->save();

        return response()->json([
            'success' => true,
            'message' => 'Feature Updated Successfully!',
            'data' => [
                'feature' => new CustomFeatureResource($customFeature),
            ],
        ], 201);
    }
}
