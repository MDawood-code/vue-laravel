<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Payment **/
class PaymentOdooResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'brand' => $this->brand,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'merchant_transaction_id' => $this->merchant_transaction_id,
            'invoice_id' => $this->invoice_id,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
