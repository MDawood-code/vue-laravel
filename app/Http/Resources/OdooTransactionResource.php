<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class OdooTransactionResource extends JsonResource
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
            'tax' => $this->tax,
            'type' => getTransactionTypeText($this->type),
            'buyer_company_name' => $this->buyer_company_name,
            'buyer_company_vat' => $this->buyer_company_vat,
            'discount_amount' => $this->discount_amount,
            'created_at' => $this->created_at,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'invoice_url' => config('frontend.frontend_url').'transactions/invoice?id='.encrypt($this->id),
            'items' => OdooTransactionItemResource::collection($this->items),
        ];
    }
}
