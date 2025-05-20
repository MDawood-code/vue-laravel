<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductCategoryWithProductsResource;
use App\Http\Resources\TransactionResource;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\ProductCategory;
use App\Models\Transaction;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

/**
 * @group Customer
 *
 * @subgroup Product
 *
 * @subgroupDescription APIs for managing Product
 */
class GetQrProducts extends Controller
{
    /**
     * Get QR Products
     *
     * @bodyParam src string required The src is the branch id. Example: 'dkfjdkfjk232'.
     *
     * @unauthenticated
     */
    public function __invoke(Request $request): JsonResponse
    {
        $src = $request->query('src');
        $tableId = $request->query('table');

        if (! is_string($src)) {
            return response()->json([
                'success' => false,
                'message' => 'Missing src.',
                'data' => [],
            ], 400);
        }

        try {
            $branchId = Crypt::decrypt($src);
            $tableId = Crypt::decrypt($tableId);
        } catch (DecryptException) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payload.',
                'data' => [],
            ], 400);
        }
        $branch = Branch::where('id', $branchId)->with('company')->first();
        if (! $branch) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Branch',
                'data' => [],
            ], 400);
        }

        if ($branch->diningTables()->where('id', $tableId)->doesntExist()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Table',
                'data' => [],
            ], 400);
        }

        if (! hasActiveQrOrderingAddon($branch->employees()->first())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to place QR Order.',
                'data' => [],
            ], 400);
        }

        // Get All Categories of current User to send in response with products
        $product_categories = ProductCategory::where('company_id', $branch->company_id)
            ->orderBy('order')
            ->orderBy('name')
            ->with('products', function ($query): void {
                $query->where('is_qr_product', true)->with('stocks');
            })
            ->get();

        $orders = Transaction::where('dining_table_id', $tableId)->whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::InProgress->value])
        ->with(['user', 'items', 'multipayments', 'waiter', 'discount', 'diningTable', 'branch', 'refundTransactions', 'referenceTransaction', 'user','customer'])
        ->get();

        return response()->json([
            'success' => true,
            'message' => 'Categories with Products List Response',
            'data' => [
                'company_name' => $branch->company->name,
                'business_type' => $branch->company->business_type,
                'logo' => $branch->company->logo ? asset($branch->company->logo) : null,
                'branch_name' => $branch->name,
                'branch_address' => $branch->address,
                'categories_products' => ProductCategoryWithProductsResource::collection($product_categories),
                'orders' => TransactionResource::collection(resource: $orders),
            ],
        ], 200);
    }
}
