<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer
 *
 * @subgroup Transaction
 *
 * @subgroupDescription APIs for managing Transaction
 */
class GetOrdersCounts extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Get counts of the orders.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Transaction::class);

        $pending_orders_count = $this->transactionsCountByStatus(TransactionStatus::Pending);
        $in_progress_orders_count = $this->transactionsCountByStatus(TransactionStatus::InProgress);
        $completed_orders_count = $this->transactionsCountByStatus(TransactionStatus::Completed);
        $cancelled_orders_count = $this->transactionsCountByStatus(TransactionStatus::Cancelled);

        return response()->json([
            'success' => true,
            'message' => 'Transactions List Response',
            'data' => [
                'pending_orders_count' => $pending_orders_count,
                'in_progress_orders_count' => $in_progress_orders_count,
                'completed_orders_count' => $completed_orders_count,
                'cancelled_orders_count' => $cancelled_orders_count,
            ],

        ], 200);
    }

    private function transactionsCountByStatus(TransactionStatus $transactionStatus): int
    {
        return $this->transactionQuery()
        ->where('status', $transactionStatus->value)
        ->whereNull('sale_invoice_status')
        ->count();
    }

    /** @return Builder<Transaction> */
    private function transactionQuery(): Builder
    {
        if (user_is_company_owner()) {
            return Transaction::where('company_id', $this->loggedInUser->company_id);
        } else {
            return Transaction::where('branch_id', $this->loggedInUser->branch_id)
                ->when($this->loggedInUser->is_waiter, function ($query): void {
                    $query->where(function ($q): void {
                        $q->where('user_id', auth()->id())
                            ->orWhere('waiter_id', auth()->id());
                    });
                });
        }
    }
}
