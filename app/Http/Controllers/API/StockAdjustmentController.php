<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\StockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentCollection;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\Stock;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentProduct;
use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Customer
 *
 * @subgroup StockAdjustment
 *
 * @subgroupDescription APIs for managing stock adjustments
 */
class StockAdjustmentController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the resource.
     *
     * @queryParam pageSize int The number of transactions to include per page. Example: 10
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockAdjustmentProduct::class);

        if ($this->loggedInUser->type == USER_TYPE_BUSINESS_OWNER) {
            $branches = $this->loggedInUser->company->branches->pluck('id');
        } else {
            $branches = [$this->loggedInUser->branch_id];
        }

        $stockAdjustments = StockAdjustment::with('branch', 'createdByUser', 'stockAdjustmentProducts.product')->withSum('stockAdjustmentProducts', 'quantity')->whereIn('branch_id', $branches)
            ->orderBy('created_at', 'desc')
            ->paginate($request->pageSize ?? PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Stock Adjustment Product List',
            'data' => [
                'stock_adjustments' => new StockAdjustmentCollection($stockAdjustments),
            ],
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StockAdjustmentRequest $request): JsonResponse
    {
        $this->authorize('create', [StockAdjustmentProduct::class, $request->integer('branch_id')]);

        $branchId = $request->input('branch_id');
        // $dateTime = $request->input('date_time');
        $note = $request->input('note');
        $stocksData = $request->input('stocks');

        try {
            DB::beginTransaction();

            $stockAdjustment = StockAdjustment::create([
                'branch_id' => $branchId,
                'date_time' => now()->toIso8601String(),
                'note' => $note,
                'created_by' => $this->loggedInUser->id,
                'reference_no' => $this->generateReferenceNo(),
            ]);

            $adjustedStocks = array_map(function (array $value) use ($branchId): array {
                $stock = Stock::updateOrCreate(
                    ['branch_id' => $branchId, 'product_id' => $value['product_id']],
                    ['quantity' => DB::raw("quantity + {$value['quantity']}"), 'created_by' => $this->loggedInUser->id]
                );

                $quantity = $value['quantity'];
                if ($stock->wasRecentlyCreated) {
                    $quantity = (int) abs($quantity);
                    $stock->quantity = $quantity;
                    $stock->save();
                }

                return [
                    'product_id' => $value['product_id'],
                    'quantity' => $quantity,
                ];
            }, $stocksData);

            $stockAdjustment->stockAdjustmentProducts()->createMany($adjustedStocks);

            DB::commit();

            $stockAdjustment = $stockAdjustment->fresh();
            $stockAdjustment->load('branch', 'createdByUser', 'stockAdjustmentProducts.product')->loadSum('stockAdjustmentProducts', 'quantity');

            return response()->json([
                'success' => true,
                'message' => 'Stock Adjustment Created Successfully!',
                'data' => new StockAdjustmentResource($stockAdjustment),
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Failed to create stock adjustment.', 'error' => $e->getMessage()], 500);
        }
    }

    protected function generateReferenceNo(): string
    {
        $lastStockAdjustmentId = (string) (StockAdjustment::max('id') + 1);

        return str_pad($lastStockAdjustmentId, 5, '0', STR_PAD_LEFT);
    }
}
