<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Transaction **/
class TransactionResourceWithoutReference extends JsonResource
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
            'order_id' => $this->order_id,
            'items' => TransactionItemResource::collection($this->items),
            'cash_collected' => $this->cash_collected,
            'amount_charged' => $this->amount_charged,
            'type' => $this->type,
            'payment_source' => $this->payment_source,
            'tax' => $this->tax,
            'tip' => $this->tip,
            'is_refunded' => $this->is_refunded,
            'status' => new EnumResource($this->status),
            'order_source' => new EnumResource($this->order_source),
            'dining_table' => new DiningTableResource($this->diningTable),
            'reference' => $this->reference,
            'payment_channel' => $this->payment_channel,
            'company_id' => $this->company_id,
            'created_at' => $this->created_at,
        ];
    }
}
