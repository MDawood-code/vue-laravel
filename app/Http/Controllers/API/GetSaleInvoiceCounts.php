<?php

namespace App\Http\Controllers\API;

use App\Enums\SaleInvoiceStatus;
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
class GetSaleInvoiceCounts extends Controller
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

        $draft_sale_invoice_count = $this->saleInvoiceCountByStatus(SaleInvoiceStatus::Draft);
        $issue_sale_invoice_count = $this->saleInvoiceCountByStatus(SaleInvoiceStatus::IssueInvoice);
        $partialPaid_sale_invoice_count = $this->saleInvoiceCountByStatus(SaleInvoiceStatus::PartialPaidInvoice);
        $paid_sale_invoice_count = $this->saleInvoiceCountByStatus(SaleInvoiceStatus::PaidInvoice);
        $cancelled_sale_invoice_count = $this->saleInvoiceCountByStatus(SaleInvoiceStatus::Cancelled);

        return response()->json([
            'success' => true,
            'message' => 'Sale Invoice List Response',
            'data' => [
                'draft_sale_invoice_count' => $draft_sale_invoice_count,
                'issue_sale_invoice_count' => $issue_sale_invoice_count,
                'partialPaid_sale_invoice_count' => $partialPaid_sale_invoice_count,
                'paid_sale_invoice_count' => $paid_sale_invoice_count,
                'cancelled_sale_invoice_count' => $cancelled_sale_invoice_count,
            ],

        ], 200);
    }

    private function saleInvoiceCountByStatus(SaleInvoiceStatus $saleInvoiceStatus): int
    {
        return $this->transactionQuery()->where('sale_invoice_status', $saleInvoiceStatus->value)->count();
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
