<?php

namespace App\Http\Controllers\API;

use App\Events\CompanyBranchForOdooCreated;
use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\CrmLog;
use App\Models\CustomFeature;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @group Customer
 *
 * @subgroup Branch
 *
 * @subgroupDescription APIs for managing Branch
 */
class BranchesController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);

        $branch = Branch::where('company_id', $this->loggedInUser->company_id)
            ->orderBy('name')
            ->get()->keyBy->id;

        return response()->json([
            'success' => true,
            'message' => 'Branches List Response',
            'data' => [
                'branches' => BranchResource::collection($branch),
            ],
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $this->authorize('create', [Branch::class, $this->loggedInUser->company]);

        $company_id = $this->loggedInUser->company_id;
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                Rule::unique('branches')->where(fn ($query) => $query->where('company_id', $company_id)
                    ->whereNull('deleted_at')),
            ],
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Some error occurred.',
                'data' => [
                    'errors' => $validator->messages()->toArray(),
                ],
            ], 400);
        }

        $branch = new Branch;
        $branch->name = $request->name;
        $branch->address = $request->address;
        $branch->code = $request->code;
        $branch->company_id = $company_id;
        $branch->save();

        if (hasActiveStockAddon($branch->company->owner)) {
            $branch->createStocksForBranch();
        }

        CrmLog::create([
            'company_id' => $company_id,
            'action' => 'A branch was added',
        ]);

        CompanyBranchForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $branch
        );

        return response()->json([
            'success' => true,
            'message' => 'Branch Added Successfully!',
            'data' => [
                'branch' => new BranchResource($branch),
            ],
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return JsonResponse
     */
    public function update(Request $request, Branch $branch)
    {
        $this->authorize('update', $branch);

        $company_id = $this->loggedInUser->company_id;
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                Rule::unique('branches')->where(fn ($query) => $query->where('company_id', $company_id)
                    ->where('id', '!=', $branch->id)
                    ->whereNull('deleted_at')),
            ],
            'address' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Some error occurred.',
                'data' => [
                    'errors' => $validator->messages()->toArray(),
                ],
            ], 400);
        }

        $branch->name = $request->name;
        $branch->address = $request->address;
        $branch->code = $request->code;
        $branch->save();

        CompanyBranchForOdooCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $branch
        );

        return response()->json([
            'success' => true,
            'message' => 'Branch Updated Successfully!',
            'data' => [
                'branch' => new BranchResource($branch),
            ],
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $company_id = $branch->company_id;
        if ($company_id === $this->loggedInUser->company_id
            && $this->loggedInUser->type === USER_TYPE_BUSINESS_OWNER
        ) {
            if ($branch->employees()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch cannot be deleted due to existence of related resources(employees).',
                    'data' => [],
                ], 500);
            }
            $productStocks = $branch->stocks();
            $branch->delete();

            if (hasActiveStockAddon($branch->company->owner)) {
                $productStocks->delete();
            }

            CrmLog::create([
                'company_id' => $company_id,
                'action' => 'A branch was deleted',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch has been deleted.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'You are not allowed to delete this entity.',
            'data' => [],
        ], 403);
    }

    /**
     * Send branch to Odoo.
     */
    public function sendToOdoo(Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        if ($this->loggedInUser->company_id === $branch->company_id) {
            CompanyBranchForOdooCreated::dispatchIf(
                $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
                $branch
            );

            return response()->json([
                'success' => true,
                'message' => 'Branch is being sent to Odoo.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid Branch',
            'data' => [],
        ], 403);
    }
}
