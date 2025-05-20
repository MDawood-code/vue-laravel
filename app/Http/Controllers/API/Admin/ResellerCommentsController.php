<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResellerComment\StoreResellerCommentRequest;
use App\Http\Resources\ResellerCommentResource;
use App\Models\ResellerComment;
use Illuminate\Http\JsonResponse;

class ResellerCommentsController extends Controller
{
    /**
     * Store a newly created comment .
     */
    public function store(StoreResellerCommentRequest $request): JsonResponse
    {
        $data = $request->validated() + ['created_by' => auth()->id()];
        $comment = ResellerComment::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Comment created successfully.',
            'data' => [
                'comment' => new ResellerCommentResource($comment),
            ],
        ]);
    }
}
