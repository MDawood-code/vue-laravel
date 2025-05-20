<?php

namespace App\Listeners;

use App\Events\InvoiceGenerated;
use App\Http\Resources\InvoiceCompactResource;
use App\Mail\OdooInvoiceCreationFailed;
use App\Models\CrmLog;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendInvoiceToOdoo implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(InvoiceGenerated $event): void
    {
        // Send details only that Odoo handles
        $invoiceDetails = $event->invoice->details->filter(fn ($invoiceDetail): bool => $invoiceDetail->odoo_product_code !== null)->values();

        $invoice = new Invoice;
        $invoice->id = $event->invoice->id;
        $invoice->uid = $event->invoice->uid;
        $invoice->amount_charged = $event->invoice->amount_charged;
        $invoice->status = $event->invoice->status;
        $invoice->setRelation('details', $invoiceDetails);
        $invoice->manually_paid_reason = $event->invoice->manually_paid_reason;
        $invoice->subscription_id = $event->invoice->subscription_id;
        $invoice->company_id = $event->invoice->company_id;
        $invoice->created_at = $event->invoice->created_at;
        // Call api for odoo that invoice has been generated
        try {
            Log::debug('Invoice Data Sent:');
            Log::debug(json_encode(['invoice' => new InvoiceCompactResource($invoice)]) ?: 'Error encoding JSON');
            $response = Http::post(config('odoo.base_url').'createinvoice', ['invoice' => new InvoiceCompactResource($invoice)]);
            Log::debug('Odoo Invoice Created API');
            Log::debug($response->body());
            $odoo_response = json_decode($response->body());
            if ($odoo_response->result && $odoo_response->result->status == true) {
                $invoice = Invoice::find($invoice->id);
                $invoice->odoo_uid = $odoo_response->result->odoo_invoice_number;
                $invoice->odoo_invoice_url = $odoo_response->result->invoice_url;
                $invoice->save();

                CrmLog::create([
                    'company_id' => $event->invoice->company_id,
                    'action' => 'Invoice created on Odoo',
                ]);
            }
        } catch (Throwable $th) {
            Log::debug('Odoo Invoice Created API FAILED');
            Mail::to(env('REPORTING_EMAIL'))->send(new OdooInvoiceCreationFailed($invoice, $th->getMessage()));

            CrmLog::create([
                'company_id' => $event->invoice->company_id,
                'action' => 'Invoice creation on Odoo Failed',
            ]);
        }
    }
}
