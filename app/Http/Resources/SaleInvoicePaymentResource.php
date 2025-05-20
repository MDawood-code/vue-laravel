<?php

namespace App\Http\Resources;

use Override;
use Illuminate\Http\Request;
use App\Models\SaleInvoicePayment;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleInvoicePaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        // Get the transaction
        $transaction = $this->transaction;
    
        $previousPayments = SaleInvoicePayment::where('transaction_id', $this->transaction_id)
            ->where('id', '<=', $this->id)
            ->sum('payment');
        $chargedAmount = floatval($transaction->amount_charged);
        $balance = $chargedAmount - floatval($previousPayments);
        $balance = round($balance, 1);
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'payment' => $this->payment,
            'payment_method' => $this->payment_method,
            'created_by' => [
                'id' => $this->createdBy->id ?? null,
                'name' => $this->createdBy->name ?? null,
            ],
            'balance' => $balance, 
            'created_at' => $this->created_at ? $this->created_at->toISOString() : null,
            // 'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
    
    
}
