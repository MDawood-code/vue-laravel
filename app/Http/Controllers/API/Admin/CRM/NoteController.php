<?php

namespace App\Http\Controllers\API\Admin\CRM;

use App\Http\Controllers\Controller;
use App\Http\Requests\Note\StoreNoteRequest;
use App\Http\Requests\Note\UpdateNoteRequest;
use App\Http\Resources\CommentCollection;
use App\Http\Resources\NoteCollection;
use App\Http\Resources\NoteResource;
use App\Models\Activity;
use App\Models\Comment;
use App\Models\Company;
use App\Models\Note;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin CRM
 *
 * @subgroup Notes
 *
 * @subgroupDescription APIs for managing Notes
 */
class NoteController extends Controller
{
    /**
     * Display a listing of the Note resource.
     *
     * @queryParam page int Page number to show. Defaults to 1.
     */
    public function index(Company $company): JsonResponse
    {
        $this->authorize('viewAny', [Activity::class, $company]);
        $notes = $company->notes()
            ->with('createdByUser', 'comments.createdByUser')
            ->withCount('comments')
            ->latest()
            ->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Notes',
            'data' => new NoteCollection($notes),
        ]);
    }

    /**
     * Store a newly created Note resource in storage.
     */
    public function store(StoreNoteRequest $request, Company $company): JsonResponse
    {
        $data = $request->validated() + ['created_by' => auth()->id()];
        $note = $company->notes()->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully.',
            'data' => [
                'note' => new NoteResource($note),
            ],
        ]);
    }

    /**
     * Display the specified Note resource.
     */
    public function show(Company $company, Note $note): JsonResponse
    {
        $this->authorize('view', [$note, $company->id]);
        $note->load('createdByUser', 'comments.createdByUser');

        return response()->json([
            'success' => true,
            'message' => 'Note',
            'data' => [
                'note' => new NoteResource($note),
            ],
        ]);
    }

    /**
     * Update the specified Note resource in storage.
     */
    public function update(UpdateNoteRequest $request, Company $company, Note $note): JsonResponse
    {
        $this->authorize('update', [$note, $company->id]);
        $note->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully.',
            'data' => [
                'note' => new NoteResource($note),
            ],
        ]);
    }

    /**
     * Remove the specified Note resource from storage.
     */
    public function destroy(Company $company, Note $note): JsonResponse
    {
        $this->authorize('delete', [$note, $company->id]);
        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully.',
            'data' => [],
        ]);
    }

    /**
     * Display a listing the comments of the specified note.
     */
    public function comments(Note $note): JsonResponse
    {
        $this->authorize('viewAny', [Comment::class, $note->company]);
        $comments = $note->comments()
            ->with('createdByUser')
            ->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Note comments',
            'data' => new CommentCollection($comments),
        ]);
    }
}
