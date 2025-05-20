<?php

namespace App\Http\Resources;

use App\Models\TransactionItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin TransactionItem **/
class OdooTransactionItemResource extends JsonResource
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
            'name' => $this->name,
            'name_en' => $this->name_en,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'tax' => $this->tax,
            'subtotal' => $this->subtotal,
            'category' => $this->category,
            'unit' => $this->unit,
            'barcode' => $this->barcode,
            'image' => $this->image,
            'product_id' => $this->product_id,
            'odoo_reference_id' => $this->product->odoo_reference_id,
        ];
    }
}
