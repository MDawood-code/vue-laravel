<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branch\StoreBranchRequest;
use App\Http\Requests\Branch\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

/**
 * @group Admin
 *
 * @subgroup Branch
 *
 * @subgroupDescription APIs for managing Branch
 */
class BranchesController extends Controller
{
    /**
     * Store new branch for a given company
     */
    public function store(StoreBranchRequest $request, Company $company): JsonResponse
    {
        $this->authorize('create', [Branch::class, $company]);

        $branch = Branch::create($request->validated() + ['company_id' => $company->id]);

        $company->crmLogs()->create([
            'created_by' => auth()->id(),
            'action' => 'added a branch',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Branch Added Successfully!',
            'data' => [
                'branch' => new BranchResource($branch),
            ],
        ], 201);
    }

    /**
     * Update the specified branch.
     *
     * @return JsonResponse
     */
    public function update(UpdateBranchRequest $request, Company $company, Branch $branch)
    {
        $this->authorize('update', $branch);

        $branch->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Branch Updated Successfully!',
            'data' => [
                'branch' => new BranchResource($branch),
            ],
        ], 201);
    }

    /**
     * Remove the specified branch
     */
    public function destroy(Company $company, Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        if ($branch->employees()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Branch cannot be deleted due to existence of related resources(employees).',
                'data' => [],
            ], 500);
        }

        $branch->delete();

        $company->crmLogs()->create([
            'created_by' => auth()->id(),
            'action' => 'deleted a branch',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Branch has been deleted.',
            'data' => [],
        ], 200);
    }
}
