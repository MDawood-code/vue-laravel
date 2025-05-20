<?php

namespace App\Http\Controllers\API\Admin;

use App\Events\CompanyActivated;
use App\Events\InvoiceGenerated;
use App\Events\PaymentVerified;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Admin
 *
 * @subgroup FailedOdooResources
 *
 * @subgroupDescription APIs for managing FailedOdooResources
 */
class SendFailedOdooResources extends Controller
{
    /**
     * Send failed odoo resources
     */
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('sendFailedOdooResources', Company::class);

        // Accounts not created on Odoo
        $failed_odoo_accounts = Company::absentOnOdoo()->get();
        // Send to Odoo
        $failed_odoo_accounts->each(function (Company $company, int $key): void {
            CompanyActivated::dispatch($company);
        });

        // Invoices not stored on Odoo
        $failed_odoo_invoices = Invoice::absentOnOdoo()->get();
        // Send to Odoo
        $failed_odoo_invoices->each(function (Invoice $invoice, int $key): void {
            InvoiceGenerated::dispatch($invoice);
        });

        // Payments not stored on Odoo
        $failed_odoo_payments = Payment::done()->absentOnOdoo()->get();
        // Send to Odoo
        $failed_odoo_payments->each(function (Payment $payment, int $key): void {
            PaymentVerified::dispatch($payment);
        });

        return response()->json([
            'success' => true,
            'message' => 'Processing completed.',
            'data' => [],
        ], 201);
    }
}
