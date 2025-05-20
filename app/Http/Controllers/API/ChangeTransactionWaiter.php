<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\ChangeTransactionWaiterRequest;
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
class ChangeTransactionWaiter extends Controller
{
    /**
     * Change waiter of order.
     */
    public function __invoke(ChangeTransactionWaiterRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        if (auth()->guard('api')->user()->company->status != COMPANY_STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'You are not an active user.',
                'data' => [],
            ], 400);
        }

        if (! hasActiveWaiterManagementAddon($transaction->company->owner) && ! hasActiveJobManagementAddon($transaction->company->owner)) {
            return response()->json([
                'success' => false,
                'message' => 'You have not subscribed Waiter Management addon.',
                'data' => [],
            ], 400);
        }

        $transaction->waiter_id = $request->has('waiter_id') ? $request->waiter_id : null;

        $transaction->save();
        $transaction->refresh();

        $transaction->load(['items', 'multipayments', 'waiter', 'discount', 'diningTable', 'branch', 'refundTransactions', 'referenceTransaction', 'user']);

        return response()->json([
            'success' => true,
            'message' => 'Waiter has been changed successfully.',
            'data' => ['transaction' => new TransactionResource($transaction)],
        ], 201);
    }
}
