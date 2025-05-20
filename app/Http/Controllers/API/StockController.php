<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchStockResource;
use App\Http\Resources\StockResource;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer
 *
 * @subgroup Stock
 *
 * @subgroupDescription APIs for managing Stock
 */
class StockController extends Controller
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
        $this->authorize('viewAny', Stock::class);

        $stock = Stock::with(['branch', 'product.stocks.branch', 'createdByUser'])->where('branch_id', $this->loggedInUser->branch_id)->get();

        return response()->json([
            'success' => true,
            'message' => 'Stock List Response',
            'data' => [
                'Stock' => StockResource::collection($stock),
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int|string $productId): JsonResponse
    {
        $this->authorize('view', [Stock::class, $productId]);
        $branches = $this->loggedInUser->company->branches->pluck('id');
        $stock = Stock::with('product')->where('product_id', $productId)->whereIn('branch_id', $branches)->get();

        return response()->json([
            'success' => true,
            'message' => 'Stock Response',
            'data' => [
                'stock' => StockResource::collection($stock),
            ],
        ]);
    }

    /**
     * Display the stock for given branch.
     */
    public function getBranchProducts(int|string $branchId): JsonResponse
    {
        $this->authorize('branchStock', [Stock::class, $branchId]);

        $products = Stock::with('product')->where('branch_id', $branchId)
            ->orderBy('id', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Product List with Stock',
            'data' => [
                'products' => BranchStockResource::collection($products),
            ],
        ], 200);
    }
}
