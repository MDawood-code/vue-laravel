<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Invoice **/
class InvoiceCompactResource extends JsonResource
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
            'uid' => $this->uid,
            'amount_charged' => $this->amount_charged,
            'status' => $this->status,
            'details' => $this->details,
            'manually_paid_reason' => $this->manually_paid_reason,
            'subscription' => $this->subscription_id,
            'company' => $this->company_id,
            'created_at' => $this->created_at,
        ];
    }
}
