<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionOrderSource;
use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;

/**
 * @group Customer
 *
 * @subgroup Transaction
 *
 * @subgroupDescription APIs for managing Transaction
 */
class PlaceQrOrder extends Controller
{
    /**
     * Place QR Order
     */
    public function __invoke(StoreTransactionRequest $request): JsonResponse
    {
        $src = $request->query('src');
        $table = $request->query('table');

        if (! is_string($src) || ! is_string($table)) {
            return response()->json(['error' => 'Missing or invalid parameters'], 400);
        }

        try {
            $branchId = Crypt::decrypt($src);
            $diningTableId = Crypt::decrypt($table);
        } catch (DecryptException) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payload.',
                'data' => [],
            ], 400);
        }

        $branch = Branch::find($branchId);
        $diningTable = DiningTable::find($diningTableId);
        if (! $branch || ! $diningTable) {
            return response()->json(['error' => 'Invalid IDs'], 400);
        }

        /** @var Branch $branch */
        if (! hasActiveQrOrderingAddon($branch->employees()->first())) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to place QR Order.',
                'data' => [],
            ], 400);
        }

        $transactionService = new TransactionService;
        $transaction = $transactionService->createTransaction(
            branch: $branch,
            data: $request,
            transactionStatus: TransactionStatus::Pending,
            transactionOrderSource: TransactionOrderSource::QrOrder,
            user: null,
            diningTableId: $diningTableId,
        );

        if (! ($transaction instanceof Transaction)) {
            return $transaction;
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction has been added successfully.',
            'data' => [],
        ], 201);
    }
}
