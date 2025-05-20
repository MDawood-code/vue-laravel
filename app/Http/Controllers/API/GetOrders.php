<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Customer
 *
 * @subgroup Transaction
 *
 * @subgroupDescription APIs for managing Transaction
 */
class GetOrders extends Controller
{
    /**
     * Get Orders.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Transaction::class);

        $loggedInUser = auth()->guard('api')->user();

        if (user_is_company_owner()) {
            $transactions = Transaction::where('company_id', $loggedInUser->company_id)->with('user');
        } else {
            $transactions = Transaction::where('branch_id', $loggedInUser->branch_id)
                ->when(auth()->user()->is_waiter, function ($query): void {
                    $query->where(function ($q): void {
                        $q->where('user_id', auth()->id())
                            ->orWhere('waiter_id', auth()->id());
                    });
                })
                ->with('user');
        }

        if ($request->search_start_date && $request->search_end_date) {
            $start_date = Carbon::parse($request->search_start_date)->toDateTimeString();
            $end_date = Carbon::parse($request->search_end_date)->toDateTimeString();
            $transactions = $transactions->where('created_at', '>=', $start_date)
                ->where('created_at', '<=', $end_date);
        }
        if (in_array($request->is_refunded, ['0', '1'])) {
            $transactions = $transactions->where('is_refunded', boolval($request->is_refunded));
        }
        if (in_array($request->type, ['1', '2', '3', '4', '5'])) {
            $transactions = $transactions->where('type', intval($request->type));
        }
        $transactions = $transactions->whereNull('sale_invoice_status');
        $transactions = $transactions->with(['items', 'multipayments'])
            ->when(request('filter_by_branch'), function ($query): void {
                $query->whereHas('branch', function ($q): void {
                    $q->where('name', request('filter_by_branch'));
                });
            })
            ->when($request->status, function ($query, $status): void {
                $query->where('status', $status);
            })
            ->when($request->order_source, function ($query, $orderSource): void {
                $query->where('order_source', $orderSource);
            })
            ->orderBy('created_at', TransactionStatus::fromOrDefault($request->status) == TransactionStatus::Completed ? 'desc' : 'asc')
            ->paginate($request->pageSize ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Transactions List Response',
            'data' => [
                'transactions' => TransactionResource::collection($transactions),
                'pagination' => [
                    'total' => $transactions->total(),
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total_pages' => ceil($transactions->total() / $transactions->perPage()),
                    'has_more_pages' => $transactions->hasMorePages(),
                    'next_page_url' => $transactions->nextPageUrl(),
                    'previous_page_url' => $transactions->previousPageUrl(),
                ],
            ],

        ], 200);
    }
}
