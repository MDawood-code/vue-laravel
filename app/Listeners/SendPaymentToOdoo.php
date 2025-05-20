<?php

namespace App\Listeners;

use App\Events\PaymentVerified;
use App\Http\Resources\PaymentOdooResource;
use App\Mail\OdooPaymentCreationFailed;
use App\Models\CrmLog;
use App\Models\Payment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPaymentToOdoo implements ShouldQueue
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
    public function handle(PaymentVerified $event): void
    {
        // Call api for odoo that invoice has been paid
        try {
            $response = Http::post(config('odoo.base_url').'createpayment', ['payment' => new PaymentOdooResource($event->payment)]);
            Log::debug('Odoo Payment Done API');
            Log::debug(json_encode(['payment' => new PaymentOdooResource($event->payment)]) ?: 'Error encoding JSON');
            Log::debug($response->body());
            $odoo_response = json_decode($response->body());
            if ($odoo_response->result && $odoo_response->result->odoo_payment_number != null) {
                $payment = Payment::find($event->payment->id);
                $payment->odoo_payment_number = $odoo_response->result->odoo_payment_number;
                $payment->save();

                CrmLog::create([
                    'company_id' => $event->payment->invoice->company_id,
                    'action' => 'Payment created on Odoo',
                ]);
            }
        } catch (Throwable $th) {
            Log::debug('Odoo Payment Done API FAILED');
            Mail::to(env('REPORTING_EMAIL'))->send(new OdooPaymentCreationFailed($event->payment, $th->getMessage()));

            CrmLog::create([
                'company_id' => $event->payment->invoice->company_id,
                'action' => 'Payment creation on Odoo Failed',
            ]);
        }
    }
}
