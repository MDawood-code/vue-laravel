<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discount\StoreDiscountRequest;
use App\Http\Requests\Discount\UpdateDiscountRequest;
use App\Http\Resources\DiscountCollection;
use App\Http\Resources\DiscountResource;
use App\Models\Discount;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer
 *
 * @subgroup Discount
 *
 * @subgroupDescription APIs for managing Discount
 */
class DiscountController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the all discounts of the company.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Discount::class);

        $discounts = $this->loggedInUser->company->discounts;

        return response()->json([
            'success' => true,
            'message' => 'Discounts List Response',
            'data' => new DiscountCollection($discounts),
        ], 200);
    }

    /**
     * Display a listing of the all discounts of the branch.
     */
    public function branchDiscounts(): JsonResponse
    {
        $this->authorize('viewAny', Discount::class);

        $discounts = $this->loggedInUser->branch->discounts;

        return response()->json([
            'success' => true,
            'message' => 'Discounts List Response',
            'data' => new DiscountCollection($discounts),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDiscountRequest $request): JsonResponse
    {
        $this->authorize('create', Discount::class);
        $discount = $this->loggedInUser->company->discounts()->create($request->safe()->only(['discount_percentage']));

        $discount->branches()->attach($request->branches);

        return response()->json([
            'success' => true,
            'message' => 'Discounts created',
            'data' => [
                'discount' => new DiscountResource($discount),
            ],
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDiscountRequest $request, Discount $discount): JsonResponse
    {
        $this->authorize('update', $discount);
        $discount->discount_percentage = $request->safe()['discount_percentage'];
        $discount->save();

        $discount->branches()->sync($request->branches);

        return response()->json([
            'success' => true,
            'message' => 'Discounts updated',
            'data' => [
                'discount' => new DiscountResource($discount),
            ],
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Discount $discount): JsonResponse
    {
        $this->authorize('delete', $discount);
        $discount->branches()->detach();
        $discount->delete();

        return response()->json([
            'success' => true,
            'message' => 'Discounts deleted',
            'data' => [],
        ], 200);
    }
}
