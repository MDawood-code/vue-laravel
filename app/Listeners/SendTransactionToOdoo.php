<?php

namespace App\Listeners;

use App\Models\ExternalIntegration;
use App\Events\OdooTransactionCreated;
use App\Http\Resources\OdooTransactionResource;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTransactionToOdoo implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OdooTransactionCreated $event): void
    {
        $transaction = new OdooTransactionResource($event->transaction);

        // Call api for odoo that invoice has been generated
        try {
            Log::debug('Transaction Data Sent:');
            Log::debug(json_encode(['transaction' => $transaction]) ?: 'Error encoding JSON');
            /** @var ExternalIntegration $odooIntegration */
            $odooIntegration = $event->transaction->company->odooIntegration();
            $data = [
                'token' => $odooIntegration->secret_key,
                'transaction' => $transaction,
            ];
            if ($event->transaction->is_refunded) {
                $data['is_refunded'] = true;
                $data['odoo_reference_number'] = $event->transaction->referenceTransaction->odoo_reference_number;
            }
            $response = Http::post($odooIntegration->url.'/transactions/create', $data);
            Log::debug('Odoo Transaction Created API');
            Log::debug($response->body());
            $odoo_response = json_decode($response->body());
            if ($odoo_response->result && $odoo_response->result->status == true) {
                /** @var Transaction $transaction */
                $transaction = Transaction::find($transaction->id);
                $transaction->odoo_reference_number = $odoo_response->result->odoo_invoice_id;
                $transaction->save();
            }
        } catch (Throwable) {
            Log::debug('Odoo Transaction Created API FAILED');
        }
    }
}
