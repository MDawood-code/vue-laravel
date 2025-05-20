<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\ChangeTransactionTableRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

/**
 * @group Customer
 *
 * @subgroup Transaction
 *
 * @subgroupDescription APIs for managing Transaction
 */
class ChangeTransactionTable extends Controller
{
    /**
     * Change dining table of transaction.
     */
    public function __invoke(ChangeTransactionTableRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        if (auth()->guard('api')->user()->company->status != COMPANY_STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'You are not an active user.',
                'data' => [],
            ], 400);
        }

        if (! hasActiveTableManagementAddon($transaction->company->owner)) {
            return response()->json([
                'success' => false,
                'message' => 'You have not subscribed Table Management addon.',
                'data' => [],
            ], 400);
        }

        $transaction->dining_table_id = $request->has('dining_table_id') ? $request->dining_table_id : null;

        $transaction->save();
        $transaction->refresh();

        $transaction->load(['items', 'multipayments', 'waiter', 'discount', 'diningTable', 'branch', 'refundTransactions', 'referenceTransaction', 'user']);

        return response()->json([
            'success' => true,
            'message' => 'Table has been changed successfully.',
            'data' => ['transaction' => new TransactionResource($transaction)],
        ], 201);
    }
}
