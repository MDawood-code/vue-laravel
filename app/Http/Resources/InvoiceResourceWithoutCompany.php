<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Invoice **/
class InvoiceResourceWithoutCompany extends JsonResource
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
            'subscription' => $this->subscription,
            'devices' => DeviceResource::collection($this->devices),
            'created_at' => $this->created_at,
            'odoo_invoice_url' => $this->odoo_invoice_url ? config('odoo.base_url').$this->odoo_invoice_url : null,
            'stcpay_reference_id' => $this->stcpay_reference_id,
            'payment_brand' => $this->whenLoaded('payments', fn () => $this->payments->sortByDesc('id')->first()?->brand),
        ];
    }
}
