<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\IssueType\StoreIssueTypeRequest;
use App\Http\Requests\IssueType\UpdateIssueTypeRequest;
use App\Http\Resources\IssueTypeCollection;
use App\Http\Resources\IssueTypeResource;
use App\Models\IssueType;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 *
 * @subgroup IssueType
 *
 * @subgroupDescription APIs for managing IssueType
 */
class IssueTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', IssueType::class);

        $issue_types = IssueType::paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Issue Types',
            'data' => new IssueTypeCollection($issue_types),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreIssueTypeRequest $request): JsonResponse
    {
        $this->authorize('create', IssueType::class);

        $issue_type = IssueType::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Issue Type created successfully.',
            'data' => new IssueTypeResource($issue_type),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(IssueType $issueType): JsonResponse
    {
        $this->authorize('view', $issueType);

        return response()->json([
            'success' => true,
            'message' => 'Issue Type Response.',
            'data' => [
                'device' => new IssueTypeResource($issueType),
            ],
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateIssueTypeRequest $request, IssueType $issueType): JsonResponse
    {
        $this->authorize('update', $issueType);

        $issueType->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Issue Type Updated Successfully!',
            'data' => [
                'issue_type' => new IssueTypeResource($issueType),
            ],
        ], 201);
    }
}
