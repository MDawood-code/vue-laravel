<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\LearningSourceResource;
use App\Models\LearningSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin
 *
 * @subgroup LearningSource
 *
 * @subgroupDescription APIs for managing LearningSource
 */
class LearningSourceController extends Controller
{
    /**
     * Display a list of the learning sources
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LearningSource::class);

        $learningSources = LearningSource::all();

        return response()->json([
            'success' => true,
            'message' => 'Questionnaire',
            'data' => [
                'learning_sources' => LearningSourceResource::collection($learningSources),
            ],
        ]);
    }
}
