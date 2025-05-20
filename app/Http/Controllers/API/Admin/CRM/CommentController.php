<?php

namespace App\Http\Controllers\API\Admin\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Http\Requests\Comment\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin CRM
 *
 * @subgroup CRM Comment
 *
 * @subgroupDescription APIs for managing CRM Comment
 */
class CommentController extends Controller
{
    /**
     * Store a newly created comment resource in storage.
     */
    public function store(StoreCommentRequest $request): JsonResponse
    {
        $data = $request->validated() + ['created_by' => auth()->id()];
        $comment = Comment::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Comment created successfully.',
            'data' => [
                'comment' => new CommentResource($comment),
            ],
        ]);
    }

    /**
     * Display the specified comment resource.
     */
    public function show(Comment $comment): JsonResponse
    {
        $this->authorize('view', $comment);

        return response()->json([
            'success' => true,
            'message' => 'Comment',
            'data' => [
                'comment' => new CommentResource($comment),
            ],
        ]);
    }

    /**
     * Update the specified comment resource in storage.
     */
    public function update(UpdateCommentRequest $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);
        $comment->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Comment updated successfully.',
            'data' => [
                'comment' => new CommentResource($comment),
            ],
        ]);
    }

    /**
     * Remove the specified comment resource from storage.
     */
    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully.',
            'data' => [],
        ]);
    }
}
