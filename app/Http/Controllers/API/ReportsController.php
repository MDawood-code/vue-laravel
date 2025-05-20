<?php

namespace App\Http\Controllers\API;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionMultipayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @group Customer
 *
 * @subgroup Reports
 *
 * @subgroupDescription APIs for managing Reports
 */
class ReportsController extends Controller
{
    protected ?User $loggedInUser;

    protected ?string $start_date = null;

    protected ?string $end_date = null;

    public function __construct(Request $request)
    {
        $this->loggedInUser = auth()->guard('api')->user();
        if ($request->search_start_date && $request->search_end_date) {
            $this->start_date = Carbon::parse($request->search_start_date)->toDateTimeString();
            $this->end_date = Carbon::parse($request->search_end_date)->toDateTimeString();
        }
    }

    /**
     * Get sales summary report.
     *
     * @queryParam search_start_date date optional The starting date of search.
     * @queryParam search_end_date date optional The ending date of search.
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $this->authorize('salesSummary', Transaction::class);

        $refundedTransactionsTotal = Transaction::select(DB::raw('SUM(amount_charged) as refunded'))
            ->whereNotNull('refunded_transaction_id')
            ->completed()
            ->where('company_id', $this->loggedInUser->company_id);
        $transactionsTotal = Transaction::select(DB::raw('SUM(amount_charged) as total_sales, SUM(tax) as taxes'))
            ->whereNull('refunded_transaction_id')
            ->completed()
            ->where('company_id', $this->loggedInUser->company_id);

        $transactionsByType = Transaction::select(DB::raw('type, SUM(amount_charged) as total_charged_amount, SUM(tax) as total_tax'))
            ->whereNull('refunded_transaction_id')
            ->completed()
            ->where('company_id', $this->loggedInUser->company_id);
        $transactionsByMultipayment = TransactionMultipayment::whereHas('transaction', function ($query): void {
            $query->whereNull('refunded_transaction_id')
                ->where('company_id', $this->loggedInUser->company_id);
        })->select(DB::raw('transaction_type, SUM(amount) as total_charged_amount'));

        $refundedByType = Transaction::select(DB::raw('type, SUM(amount_charged) as total_refunded_amount'))
            ->whereNotNull('refunded_transaction_id')
            ->completed()
            ->where('company_id', $this->loggedInUser->company_id);

        if ($this->start_date && $this->end_date) {
            $refundedTransactionsTotal = $refundedTransactionsTotal->where('created_at', '>=', $this->start_date)
                ->where('created_at', '<=', $this->end_date);
            $transactionsTotal = $transactionsTotal->where('created_at', '>=', $this->start_date)
                ->where('created_at', '<=', $this->end_date);
            $transactionsByType = $transactionsByType->where('created_at', '>=', $this->start_date)
                ->where('created_at', '<=', $this->end_date);
            $transactionsByMultipayment = $transactionsByMultipayment->where('created_at', '>=', $this->start_date)
                ->where('created_at', '<=', $this->end_date);
            $refundedByType = $refundedByType->where('created_at', '>=', $this->start_date)
                ->where('created_at', '<=', $this->end_date);
        }

        if ($this->loggedInUser->isEmployee) {
            $refundedTransactionsTotal = $refundedTransactionsTotal->where('branch_id', $this->loggedInUser->branch_id);
            $transactionsTotal = $transactionsTotal->where('branch_id', $this->loggedInUser->branch_id);
            $transactionsByType = $transactionsByType->where('branch_id', $this->loggedInUser->branch_id);
            $transactionsByMultipayment = $transactionsByMultipayment->whereHas('transaction', function ($query): void {
                $query->where('branch_id', $this->loggedInUser->branch_id);
            });
            $refundedByType = $refundedByType->where('branch_id', $this->loggedInUser->branch_id);
        }

        $refundedTransactionsTotal = $refundedTransactionsTotal->first();
        $transactionsTotal = $transactionsTotal->first();
        $transactionsByType = $transactionsByType->groupBy('type')->get();
        $transactionsByMultipayment = $transactionsByMultipayment->groupBy('transaction_type')->get();
        $refundedByType = $refundedByType->groupBy('type')->get();

        return response()->json([
            'success' => true,
            'message' => 'Sales Summary Report',
            'data' => [
                'transactions_by_type' => $transactionsByType,
                'transactions_by_multipayment' => $transactionsByMultipayment,
                'refunded_by_type' => $refundedByType,
                'transactions_total' => $transactionsTotal,
                'refunded_transactions_total' => $refundedTransactionsTotal->refunded,
            ],
        ]);
    }

    /**
     * Get sales by items report.
     *
     * @queryParam search_start_date date optional The starting date of search.
     * @queryParam search_end_date date optional The ending date of search.
     * @queryParam filter_by_branch string optional Filter sales by branch.
     */
    public function salesByItems(Request $request): JsonResponse
    {
        $this->authorize('salesByItems', Transaction::class);

        $company_id = $this->loggedInUser->company_id;

        // Initially the query was like this
        // $salesByItems = TransactionItem::select(
        //     DB::raw('transaction_items.name,
        //     transaction_items.barcode,
        //     transaction_items.category,
        //     transaction_items.unit,
        //     COUNT(transaction_items.quantity) as quantity_sold,
        //     SUM(transaction_items.subtotal) as net_amount,
        //     SUM(transaction_items.tax) as total_tax,
        //     branches.name as branch_name'))
        //     ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
        //     ->join('branches', 'transactions.branch_id', '=', 'branches.id')
        //     ->whereNull('transactions.refunded_transaction_id')
        //     ->where('transactions.company_id', $company_id)
        //     ->when($this->loggedInUser->isEmployee, function ($query) {
        //         $query->where('transactions.branch_id', $this->loggedInUser->branch_id);
        //     })
        //     ->when($this->start_date && $this->end_date, function ($query) {
        //         $query->where('transactions.created_at', '>=', $this->start_date)
        //             ->where('transactions.created_at', '<=', $this->end_date);
        //     })
        //     ->groupBy('transaction_items.name', 'branches.name')
        //     ->orderBy('transaction_items.name')
        //     ->get();

        // Now considering discount applied on transaction level
        $salesByItems = TransactionItem::select(
            DB::raw('CONCAT(transaction_items.name, "-", branches.name) as record_key,
            transaction_items.name,
            transaction_items.barcode,
            transaction_items.category,
            transaction_items.unit,
            COUNT(transaction_items.quantity) as quantity_sold,
            SUM(transaction_items.subtotal) - SUM(transaction_items.subtotal * transactions.discount_amount / (SELECT SUM(transaction_items.subtotal) from transaction_items WHERE transaction_items.transaction_id = transactions.id)) as net_amount,
            SUM(transaction_items.tax) as total_tax,
            branches.name as branch_name'))
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('branches', 'transactions.branch_id', '=', 'branches.id')
            ->whereNull('transactions.refunded_transaction_id')
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.company_id', $company_id)
            ->when($this->loggedInUser->isEmployee, function ($query): void {
                $query->where('transactions.branch_id', $this->loggedInUser->branch_id);
            })
            ->when($this->start_date && $this->end_date, function ($query): void {
                $query->where('transactions.created_at', '>=', $this->start_date)
                    ->where('transactions.created_at', '<=', $this->end_date);
            })
            ->groupBy('transaction_items.name', 'branches.name')
            // ->orderBy('transaction_items.name')
            // ->when(request('sort') == 'branch', function ($query) {
            //     $query->orderBy('branches.name', request('order', 'asc'));
            // })
            ->when(request('filter_by_branch'), function ($query): void {
                $query->where('branches.name', request('filter_by_branch'));
            })
            ->get();
        Log::debug($salesByItems);

        return response()->json([
            'success' => true,
            'message' => 'Sales By Items Report',
            'data' => [
                'sales_by_items' => $salesByItems,
            ],
        ]);
    }

    /**
     * Get sales by categories report.
     *
     * @queryParam search_start_date date optional The starting date of search.
     * @queryParam search_end_date date optional The ending date of search.
     * @queryParam filter_by_branch string optional Filter sales by branch.
     */
    public function salesByCategories(Request $request): JsonResponse
    {
        $this->authorize('salesByCategories', Transaction::class);

        $company_id = $this->loggedInUser->company_id;
        // Initially the query was like this
        // $salesByCategories = TransactionItem::select(
        //     DB::raw('category,
        //     COUNT(quantity) as quantity_sold,
        //     SUM(subtotal) as net_amount,
        //     SUM(tax) as total_tax'))
        //     ->whereHas('transaction', function ($query) use ($company_id) {
        //         $query->whereNull('refunded_transaction_id')
        //             ->where('company_id', $company_id);
        //         if ($this->loggedInUser->isEmployee) {
        //             $query->where('branch_id', $this->loggedInUser->branch_id);
        //         }
        //         if ($this->start_date && $this->end_date) {
        //             $query->where('created_at', '>=', $this->start_date)
        //                 ->where('created_at', '<=', $this->end_date);
        //         }
        //     })
        //     ->groupBy('category')
        //     ->get();

        // Now considering discount applied on transaction level
        $salesByCategories = TransactionItem::select(
            DB::raw('transaction_items.category,
            COUNT(transaction_items.quantity) as quantity_sold,
            SUM(transaction_items.subtotal) - SUM(transaction_items.subtotal * transactions.discount_amount / (SELECT SUM(transaction_items.subtotal) from transaction_items WHERE transaction_items.transaction_id = transactions.id)) as net_amount,
            SUM(transaction_items.tax) as total_tax'))
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('branches', 'transactions.branch_id', '=', 'branches.id')
            ->whereNull('transactions.refunded_transaction_id')
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.company_id', $company_id)
            ->when($this->loggedInUser->isEmployee, function ($query): void {
                $query->where('transactions.branch_id', $this->loggedInUser->branch_id);
            })
            ->when($this->start_date && $this->end_date, function ($query): void {
                $query->where('transactions.created_at', '>=', $this->start_date)
                    ->where('transactions.created_at', '<=', $this->end_date);
            })
            ->when(request('filter_by_branch'), function ($query): void {
                $query->where('branches.name', request('filter_by_branch'));
            })
            ->groupBy('category')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Sales By Categories Report',
            'data' => [
                'sales_by_categories' => $salesByCategories,
            ],
        ]);
    }

    /**
     * Get refunds by items report.
     *
     * @queryParam search_start_date date optional The starting date of search.
     * @queryParam search_end_date date optional The ending date of search.
     */
    public function refundsByItems(Request $request): JsonResponse
    {
        $this->authorize('refundsByItems', Transaction::class);

        $company_id = $this->loggedInUser->company_id;
        // Initially the query was like this
        // $refundsByItems = TransactionItem::select(
        //     DB::raw('name,
        //     barcode,
        //     category,
        //     unit,
        //     COUNT(quantity) as quantity_sold,
        //     SUM(subtotal) as net_amount,
        //     SUM(tax) as total_tax'))
        //     ->whereHas('transaction', function ($query) use ($company_id) {
        //         $query->whereNotNull('refunded_transaction_id')
        //             ->where('company_id', $company_id);
        //         if ($this->loggedInUser->isEmployee) {
        //             $query->where('branch_id', $this->loggedInUser->branch_id);
        //         }
        //         if ($this->start_date && $this->end_date) {
        //             $query->where('created_at', '>=', $this->start_date)
        //                 ->where('created_at', '<=', $this->end_date);
        //         }
        //     })
        //     ->groupBy('name')
        //     ->get();

        // Now considering discount applied on transaction level
        $refundsByItems = TransactionItem::select(
            DB::raw('transaction_items.name,
            transaction_items.barcode,
            transaction_items.category,
            transaction_items.unit,
            COUNT(transaction_items.quantity) as quantity_sold,
            SUM(transaction_items.subtotal) + SUM(transaction_items.subtotal * transactions.discount_amount / (SELECT SUM(transaction_items.subtotal) from transaction_items WHERE transaction_items.transaction_id = transactions.id)) as net_amount,
            SUM(transaction_items.tax) as total_tax'))
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('branches', 'transactions.branch_id', '=', 'branches.id')
            ->whereNotNull('transactions.refunded_transaction_id')
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.company_id', $company_id)
            ->when($this->loggedInUser->isEmployee, function ($query): void {
                $query->where('transactions.branch_id', $this->loggedInUser->branch_id);
            })
            ->when($this->start_date && $this->end_date, function ($query): void {
                $query->where('transactions.created_at', '>=', $this->start_date)
                    ->where('transactions.created_at', '<=', $this->end_date);
            })
            ->groupBy('transaction_items.name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Refunds By Items Report',
            'data' => [
                'refunds_by_items' => $refundsByItems,
            ],
        ]);
    }

    /**
     * Get refunds by categories report.
     *
     * @queryParam search_start_date date optional The starting date of search.
     * @queryParam search_end_date date optional The ending date of search.
     */
    public function refundsByCategories(Request $request): JsonResponse
    {
        $this->authorize('refundsByCategories', Transaction::class);

        $company_id = $this->loggedInUser->company_id;

        // Initially the query was like this
        // $refundsByCategories = TransactionItem::select(
        //     DB::raw('category,
        //     COUNT(quantity) as quantity_sold,
        //     SUM(subtotal) as net_amount,
        //     SUM(tax) as total_tax'))
        //     ->whereHas('transaction', function ($query) use ($company_id) {
        //         $query->whereNotNull('refunded_transaction_id')
        //             ->where('company_id', $company_id);
        //         if ($this->loggedInUser->isEmployee) {
        //             $query->where('branch_id', $this->loggedInUser->branch_id);
        //         }
        //         if ($this->start_date && $this->end_date) {
        //             $query->where('created_at', '>=', $this->start_date)
        //                 ->where('created_at', '<=', $this->end_date);
        //         }
        //     })
        //     ->groupBy('category')
        //     ->get();

        // Now considering discount applied on transaction level
        $refundsByCategories = TransactionItem::select(
            DB::raw('transaction_items.category,
            COUNT(transaction_items.quantity) as quantity_sold,
            SUM(transaction_items.subtotal) + SUM(transaction_items.subtotal * transactions.discount_amount / (SELECT SUM(transaction_items.subtotal) from transaction_items WHERE transaction_items.transaction_id = transactions.id)) as net_amount,
            SUM(transaction_items.tax) as total_tax'))
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->join('branches', 'transactions.branch_id', '=', 'branches.id')
            ->whereNotNull('transactions.refunded_transaction_id')
            ->where('transactions.status', TransactionStatus::Completed->value)
            ->where('transactions.company_id', $company_id)
            ->when($this->loggedInUser->isEmployee, function ($query): void {
                $query->where('transactions.branch_id', $this->loggedInUser->branch_id);
            })
            ->when($this->start_date && $this->end_date, function ($query): void {
                $query->where('transactions.created_at', '>=', $this->start_date)
                    ->where('transactions.created_at', '<=', $this->end_date);
            })
            ->groupBy('category')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Refunds By Categories Report',
            'data' => [
                'refunds_by_categories' => $refundsByCategories,
            ],
        ]);
    }

    /**
     * Get sales by branches report.
     *
     * @queryParam search_start_date date optional The starting date of search.
     * @queryParam search_end_date date optional The ending date of search.
     */
    public function salesByBranches(): JsonResponse
    {
        $this->authorize('salesByBranches', Transaction::class);

        $transactionsByBranch = DB::select('SELECT
            branches.name,
                COALESCE(SUM(z.charged_amount), 0) as total_charged_amount,
                COALESCE(SUM(z.tax), 0) as total_tax,
                COALESCE(SUM(z.refunded_amount), 0) as total_refunded_amount,
                COALESCE(SUM(z.refunded_tax), 0) as total_refunded_tax
            FROM branches
            LEFT JOIN(
                SELECT transactions_a.type,
                    transactions_a.branch_id,
                    transactions_a.amount_charged as charged_amount,
                    transactions_a.tax as tax,
                    transactions_b.amount_charged as refunded_amount,
                    transactions_b.tax as refunded_tax
                FROM transactions as transactions_a
                LEFT JOIN transactions as transactions_b
                    ON transactions_a.id = transactions_b.refunded_transaction_id
                WHERE transactions_a.company_id = ?
                    AND transactions_a.deleted_at IS NULL
                    AND transactions_b.deleted_at IS NULL
                    AND transactions_a.amount_charged > 0
                    AND transactions_a.status = ?
                    AND (CASE WHEN ? IS NOT NULL THEN transactions_a.created_at >= ? AND transactions_a.created_at <= ? ELSE TRUE END)
            ) as z ON branches.id = z.branch_id
            WHERE branches.company_id = ?
            AND branches.deleted_at IS NULL
            GROUP BY branches.name', [$this->loggedInUser->company_id, TransactionStatus::Completed->value, $this->start_date, $this->start_date, $this->end_date, $this->loggedInUser->company_id]);

        $typeTransactionsByBranch = DB::select('SELECT branches.name,
                SUM(if(z.type = 1, total_charged_amount + total_refunded_amount,0)) as total_charged_amount_1,
                SUM(if(z.type = 1, total_tax,0)) as total_tax_1,
                SUM(if(z.type = 2, total_charged_amount + total_refunded_amount,0)) as total_charged_amount_2,
                SUM(if(z.type = 2, total_tax,0)) as total_tax_2,
                SUM(if(z.type = 3, total_charged_amount + total_refunded_amount,0)) as total_charged_amount_3,
                SUM(if(z.type = 3, total_tax,0)) as total_tax_3,
                SUM(if(z.type = 4, total_charged_amount + total_refunded_amount,0)) as total_charged_amount_4,
                SUM(if(z.type = 4, total_tax,0)) as total_tax_4,
                SUM(if(z.type = 5, total_charged_amount + total_refunded_amount,0)) as total_charged_amount_5,
                SUM(if(z.type = 5, total_tax,0)) as total_tax_5
            FROM branches
            LEFT JOIN (
                SELECT
                    transactions_a.branch_id,
                    transactions_a.type,
                    COALESCE(SUM(transactions_a.amount_charged),0) as total_charged_amount,
                    COALESCE(SUM(transactions_a.tax),0 ) as total_tax,
                    COALESCE(SUM(transactions_b.amount_charged),0) as total_refunded_amount,
                    COALESCE(SUM(transactions_b.tax),0) as total_refunded_tax
                FROM transactions as transactions_a
                LEFT JOIN transactions as transactions_b
                    ON transactions_a.id = transactions_b.refunded_transaction_id
                WHERE transactions_a.`company_id` = ?
                    AND transactions_a.`deleted_at` IS NULL
                    AND transactions_b.`deleted_at` IS NULL
                    AND transactions_a.amount_charged > 0
                    AND transactions_a.status = ?
                    AND (CASE WHEN ? IS NOT NULL THEN transactions_a.created_at >= ? AND transactions_a.created_at <= ? ELSE TRUE END)
                GROUP BY transactions_a.branch_id, transactions_a.type
            ) as z
                ON z.branch_id = branches.id
            WHERE branches.`company_id` = ?
                AND branches.`deleted_at` IS NULL
            GROUP BY branches.name', [$this->loggedInUser->company_id, TransactionStatus::Completed->value, $this->start_date, $this->start_date, $this->end_date,  $this->loggedInUser->company_id]);

        return response()->json([
            'success' => true,
            'message' => 'Sales By Branch Report',
            'data' => [
                'transactions_by_branch' => $transactionsByBranch,
                'type_transactions_by_branch' => $typeTransactionsByBranch,
            ],
        ]);
    }

    /**
     * Get Home data summary.
     */
    public function homeDataSummary(): JsonResponse
    {
        $this->authorize('homeDataSummary', Transaction::class);

        $transactionsRefunded = Transaction::select(DB::raw('(SUM(amount_charged) - SUM(tax)) as refunded'))
            ->whereNotNull('refunded_transaction_id')
            ->completed()
            ->where('company_id', $this->loggedInUser->company_id)
            ->first();

        $transactionsTotal = Transaction::select(DB::raw('SUM(amount_charged) as total_sales, SUM(tax) as taxes'))
            ->whereNull('refunded_transaction_id')
            ->completed()
            ->where('company_id', $this->loggedInUser->company_id)
            ->first();

        $twoMonthsEarlierDate = Carbon::now()->subMonths(2)->startOfMonth()->toDateString();
        $transactionsByMonth = Transaction::select(
            DB::raw('COUNT(id) as count_transactions, SUM(amount_charged) as total_sales, SUM(tax) as taxes, DATE_FORMAT(created_at, "%M, %Y") as month_year'))
            ->whereNull('refunded_transaction_id')
            ->completed()
            ->where('created_at', '>=', $twoMonthsEarlierDate)
            ->where('company_id', $this->loggedInUser->company_id)
            ->groupBy('month_year')
            ->orderBy('created_at')
            ->get();

        $transactionsByType = Transaction::select(DB::raw('type, COUNT(id) as count'))
            ->whereNull('refunded_transaction_id')
            ->completed()
            ->where('company_id', $this->loggedInUser->company_id)
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $days15EarlierDate = Carbon::now()->subDays(15)->toDateString();
        $transactionsLast15Days = Transaction::select(DB::raw('SUM(amount_charged) as total_sales, DATE_FORMAT(created_at, "%Y-%m-%d") as ymd'))
            ->whereNull('refunded_transaction_id')
            ->completed()
            ->where('company_id', $this->loggedInUser->company_id)
            ->where('created_at', '>=', $days15EarlierDate)
            ->groupBy('ymd')
            ->get()
            ->keyBy('ymd');

        return response()->json([
            'success' => true,
            'message' => 'Refunds By Categories Report',
            'data' => [
                'totals' => [
                    'amount' => $transactionsTotal->total_sales,
                    'tax' => $transactionsTotal->taxes,
                    'refunds' => $transactionsRefunded->refunded,
                ],
                'quarterly_transactions' => $transactionsByMonth,
                'transaction_by_types' => $transactionsByType,
                'last_15_days_sale' => $transactionsLast15Days,
            ],
        ]);
    }
}
