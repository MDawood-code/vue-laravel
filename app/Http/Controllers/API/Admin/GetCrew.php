<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminStaffResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin
 *
 * @subgroup Crew
 *
 * @subgroupDescription APIs for managing Crew
 */
class GetCrew extends Controller
{
    /**
     * Get crew users.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('crew', User::class);

        $crew = User::whereIn('type', [USER_TYPE_ADMIN, USER_TYPE_ADMIN_STAFF])->get();

        return response()->json([
            'success' => true,
            'message' => 'Crew Response',
            'data' => [
                'crew' => AdminStaffResource::collection($crew),
            ],
        ]);
    }
}
