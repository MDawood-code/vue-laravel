<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\ChangeTransactionStatusRequest;
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
class ChangeTransactionStatus extends Controller
{
    /**
     * Change status of transaction.
     */
    public function __invoke(ChangeTransactionStatusRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        if ($request->has('status')) {
            $transaction->status = TransactionStatus::from($request->status);
        } else {
            switch ($transaction->status) {
                case TransactionStatus::Pending:
                    $transaction->status = TransactionStatus::InProgress;
                    break;
                case TransactionStatus::InProgress:
                    $transaction->status = TransactionStatus::Completed;
                    break;
                default:
                    break;
            }
        }

        $transaction->save();

        return response()->json([
            'success' => true,
            'message' => 'Transaction status updated successfully.',
            'data' => [
                'transaction' => new TransactionResource($transaction),
            ],

        ], 200);
    }
}
