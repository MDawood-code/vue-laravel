<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ApproveStockTransferRequest;
use App\Http\Requests\Stock\StockTransferRequest;
use App\Http\Resources\StockResource;
use App\Http\Resources\StockTransferCollection;
use App\Http\Resources\StockTransferResource;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockTransfer;
use App\Models\StockTransferProduct;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @group Customer
 *
 * @subgroup StockTransfer
 *
 * @subgroupDescription APIs for managing stock transfers
 */
class StockTransferController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the resource.
     *
     * @queryParam status int optional The status of the stock transfer. Values: 0, 1, 2, 3. No-example
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockTransfer::class);

        $branches = $this->getBranchesForUser($this->loggedInUser);

        $stockTransfers = StockTransfer::with(['stockTransferProducts.product', 'branchFrom', 'branchTo', 'createdByUser'])->where(function ($query) use ($branches): void {
            $query->whereIn('branch_from_id', $branches)
                ->orWhereIn('branch_to_id', $branches);
        })
            ->when($request->has('status'), fn ($query) => $query->where('status', (int) $request->status))
            ->withSum('stockTransferProducts', 'requested_quantity')
            ->withSum('stockTransferProducts', 'approved_quantity')
            ->latest()
            ->paginate(PER_PAGE_RECORDS);

        return $this->successResponse('Stock Transfer List', new StockTransferCollection($stockTransfers));
    }

    /**
     * Transfer products from one branch to another (company owner only).
     */
    public function store(StockTransferRequest $request): JsonResponse
    {
        $this->authorize('create', StockTransfer::class);
        DB::beginTransaction();
        try {
            $response = $this->processStockTransfer($request, true);
            if ($response->getData()->success) {
                DB::commit();

                return $response;
            } else {
                DB::rollBack();

                return $response;
            }
        } catch (Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to transfer stock.', 500, $e->getMessage());
        }
    }
    
    



    /**
     * Request products transfer from one branch to another.
     */
    public function request(StockTransferRequest $request): JsonResponse
    {
        $this->authorize('request', StockTransfer::class);
        DB::beginTransaction();
        try {
            $response = $this->processStockTransfer($request, false);
            if ($response->getData()->success) {
                DB::commit();

                return $response;
            } else {
                DB::rollBack();

                return $response;
            }
        } catch (Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to request stock transfer.', 500, $e->getMessage());
        }
    }

    /**
     * Approve a stock transfer request.
     *
     * @param  StockTransfer  $stockTransfer  The stock transfer object to be approved.
     */
    public function approve(ApproveStockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('approve', $stockTransfer);

        if ($stockTransfer->status != INVENTORY_REQUEST_PENDING) {
            return $this->errorResponse('This stock transfer request has already been processed.', 400);
        }

        DB::beginTransaction();
        try {
            foreach ($request->transfer_products as $transferProduct) {
                StockTransferProduct::where('id', $transferProduct['transfer_product_id'])->update(['approved_quantity' => $transferProduct['approved_quantity']]);
            }

            $stockQuantityErrors = $this->validateStockQuantities($stockTransfer->branch_from_id, $stockTransfer->stockTransferProducts, checkApprovedQuantity: true);

            if ($stockQuantityErrors !== []) {
                return $this->errorResponse('Insufficient stock quantities in the from branch.', 400, StockResource::collection($stockQuantityErrors));
            }

            $response = $this->executeStockTransfer($stockTransfer);
            if ($response->getData()->success) {
                DB::commit();

                return $response;
            } else {
                DB::rollBack();

                return $response;
            }
        } catch (Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to approve stock transfer request.', 500, $e->getMessage());
        }
    }

    /**
     * Cancel or reject a stock transfer request based on the user's branch.
     *
     * If the user belongs to the 'to_branch' of the stock transfer, the request will be cancelled.
     * If the user belongs to the 'from_branch', the request will be rejected.
     *
     * @param  StockTransfer  $stockTransfer  The stock transfer object to be cancelled or rejected.
     */
    public function cancelOrReject(StockTransfer $stockTransfer): JsonResponse
    {
        if ($stockTransfer->status != INVENTORY_REQUEST_PENDING) {
            return $this->errorResponse('Only pending stock transfer requests can be cancelled or rejected.', 400);
        }

        $user = auth()->user();

        // Check if the user is from the 'to_branch'
        if ($user->branch_id == $stockTransfer->branch_to_id) {
            $this->authorize('cancel', $stockTransfer);
            $stockTransfer->status = INVENTORY_REQUEST_CANCELLED;
        } elseif ($user->branch_id == $stockTransfer->branch_from_id) {
            $this->authorize('reject', $stockTransfer);
            $stockTransfer->status = INVENTORY_REQUEST_REJECTED;
        } else {
            // If the user does not belong to either branch, they should not be able to cancel or reject the request
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        $stockTransfer->save();

        $action = $stockTransfer->status == INVENTORY_REQUEST_CANCELLED ? 'cancelled' : 'rejected';

        return $this->successResponse(
            "Stock transfer request successfully {$action}.",
            ['stockTransfer' => new StockTransferResource($stockTransfer)]
        );
    }

    /**
     * Update the specified stock transfer.
     */
    public function update(StockTransferRequest $request, StockTransfer $stockTransfer): JsonResponse
    {
        $this->authorize('update', $stockTransfer);

        DB::beginTransaction();
        try {
            if ($stockTransfer->status != INVENTORY_REQUEST_PENDING) {
                return $this->errorResponse('Only pending stock transfer requests can be updated.', 400);
            }

            $branchFromId = $request->input('branch_from_id', $stockTransfer->branch_from_id);
            /** @var array<int, array<int|string, mixed>> $stocksData */
            $stocksData = $request->input('stocks');
            $newStocksData = collect($stocksData);

            // Validate stock quantities before proceeding
            $stockQuantityErrors = $this->validateStockQuantities($branchFromId, $newStocksData);
            if ($stockQuantityErrors !== []) {
                return $this->errorResponse('Insufficient stock quantities for one or more products.', 400, StockResource::collection($stockQuantityErrors));
            }

            // Update stock transfer details
            $stockTransfer->update([
                'branch_from_id' => $branchFromId,
                'branch_to_id' => $request->input('branch_to_id', $stockTransfer->branch_to_id),
                'date_time' => now()->format('Y-m-d H:i:s'),
                'reference_no' => $request->input('reference_no', $stockTransfer->reference_no),
            ]);

            $existingProducts = $stockTransfer->stockTransferProducts->keyBy('product_id');

            // Update, add, or remove products as necessary
            foreach ($newStocksData as $newStock) {
                $productId = $newStock['product_id'];
                $requestedQuantity = $newStock['requested_quantity'];
                $approvedQuantity = 0;

                if (isset($existingProducts[$productId])) {
                    // Update existing product quantity
                    $existingProducts[$productId]->update(['requested_quantity' => $requestedQuantity, 'approved_quantity' => $approvedQuantity]);
                } else {
                    // Add new product
                    StockTransferProduct::create([
                        'stock_transfer_id' => $stockTransfer->id,
                        'product_id' => $productId,
                        'requested_quantity' => $requestedQuantity,
                        'approved_quantity' => $approvedQuantity,
                    ]);
                }
            }

            // Remove products not present in the new list
            $newProductIds = $newStocksData->pluck('product_id');
            foreach ($existingProducts as $productId => $existingProduct) {
                if (! $newProductIds->contains($productId)) {
                    $existingProduct->delete();
                }
            }

            $stockTransfer->load('branchFrom', 'branchTo', 'stockTransferProducts.product', 'createdByUser')
                ->loadSum('stockTransferProducts', 'requested_quantity')
                ->loadSum('stockTransferProducts', 'approved_quantity');

            DB::commit();

            return $this->successResponse(
                'Stock Transfer Updated Successfully!',
                ['stockTransfer' => new StockTransferResource($stockTransfer)]
            );
        } catch (Exception $e) {
            DB::rollBack();

            return $this->errorResponse('Failed to update stock transfer request.', 500, $e->getMessage());
        }
    }

    private function processStockTransfer(StockTransferRequest $request, bool $updateStock = false): JsonResponse
    {
        $branchFromId = $request->input('branch_from_id');
        $branchToId = $request->input('branch_to_id');
        $dateTime = $request->input('date_time');
        $referenceNo = $request->input('reference_no');
        /** @var array<int, mixed> $stocksData */
        $stocksData = $request->input('stocks');

        $stockQuantityErrors = $this->validateStockQuantities($branchFromId, collect($stocksData));

        if ($stockQuantityErrors !== []) {
            return $this->errorResponse('Transfer Quantity is Greater Than Stock Quantity/ Quantity is not Accepted in Negative.', 400, StockResource::collection($stockQuantityErrors));
        }

        $status = $updateStock ? INVENTORY_REQUEST_COMPLETED : INVENTORY_REQUEST_PENDING;
        $stockTransfer = $this->createStockTransfer($branchFromId, $branchToId, $dateTime, $referenceNo, $stocksData, $status);

        $stockTransfer->load('branchFrom', 'branchTo', 'stockTransferProducts.product', 'createdByUser')
            ->loadSum('stockTransferProducts', 'requested_quantity')
            ->loadSum('stockTransferProducts', 'approved_quantity');

        if ($updateStock) {
            return $this->executeStockTransfer($stockTransfer);
        }

        return $this->successResponse(
            'Stock Transfer Request Created Successfully!',
            ['stockTransfer' => new StockTransferResource($stockTransfer)]
        );
    }

    /**
     * Summary of validateStockQuantities
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, StockTransferProduct>|Collection<int, mixed>  $stocksData
     * @return array<int, Stock|array<string, mixed>|null>
     */
    private function validateStockQuantities(int|string $branchFromId, EloquentCollection|Collection $stocksData, bool $checkApprovedQuantity = false): array
    {
        $transferQuantityName = $checkApprovedQuantity ? 'approved_quantity' : 'requested_quantity';
        $stockQuantityErrors = [];

        foreach ($stocksData as $item) {
            $stockFrom = Stock::where('branch_id', $branchFromId)->where('product_id', $item['product_id'])->first();
            if (! $stockFrom || $stockFrom->quantity < $item[$transferQuantityName] || $item[$transferQuantityName] < 0) {
                $stockQuantityErrors[] = $stockFrom ?? ['product_id' => $item['product_id']];
            }
        }

        return $stockQuantityErrors;
    }

    /**
     * Summary of createStockTransfer
     *
     * @param  array<int, mixed>  $stocksData
     */
    private function createStockTransfer(int|string $branchFromId, int|string $branchToId, string $dateTime, ?string $referenceNo, array $stocksData, int $status): StockTransfer
    {
        $stockTransfer = StockTransfer::create([
            'branch_from_id' => $branchFromId,
            'branch_to_id' => $branchToId,
            'status' => $status,
            'date_time' => $dateTime,
            'reference_no' => $referenceNo,
        ]);

        $stockTransferProductsData = array_map(fn ($item): array => [
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $item['product_id'],
            'requested_quantity' => $item['requested_quantity'],
            'approved_quantity' => $status == INVENTORY_REQUEST_COMPLETED ? $item['requested_quantity'] : 0,
        ], $stocksData);

        StockTransferProduct::insert($stockTransferProductsData);

        return $stockTransfer;
    }

    private function executeStockTransfer(StockTransfer $stockTransfer): JsonResponse
    {
        try {
            foreach ($stockTransfer->stockTransferProducts as $transferProduct) {
                $stockFrom = Stock::where('branch_id', $stockTransfer->branch_from_id)
                    ->where('product_id', $transferProduct->product_id)
                    ->firstOrFail();
                $stockFrom->decrement('quantity', $transferProduct->approved_quantity);

                Stock::updateOrCreate(
                    ['branch_id' => $stockTransfer->branch_to_id, 'product_id' => $transferProduct->product_id],
                    ['quantity' => DB::raw("GREATEST(quantity + {$transferProduct->approved_quantity}, 0)")]
                );
            }

            $stockTransfer->status = INVENTORY_REQUEST_COMPLETED;
            $stockTransfer->save();
            $stockTransfer->load('branchFrom', 'branchTo', 'stockTransferProducts.product', 'createdByUser')->loadSum('stockTransferProducts', 'requested_quantity')
                ->loadSum('stockTransferProducts', 'approved_quantity');

            return $this->successResponse(
                'Stock transfer request approved successfully.',
                ['stockTransfer' => new StockTransferResource($stockTransfer)]
            );
        } catch (Exception $e) {
            return $this->errorResponse('Failed to approve stock transfer request.', 500, $e->getMessage());
        }
    }

    /**
     * Summary of errorResponse
     *
     * @param string|array<string, mixed>|AnonymousResourceCollection $data
     */
    private function errorResponse(string $message, int $code, string|array|AnonymousResourceCollection $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Summary of successResponse
     *
     * @param array<string, StockTransferResource>|StockTransferCollection $data
     */
    private function successResponse(string $message, array|StockTransferCollection $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], 200);
    }

    /**
     * Summary of getBranchesForUser
     *
     * @return array<int, int>
     */
    private function getBranchesForUser(User $user): array
    {
        return $user->type == USER_TYPE_BUSINESS_OWNER
            ? $user->company->branches->pluck('id')->toArray()
            : [$user->branch_id];
    }
}
