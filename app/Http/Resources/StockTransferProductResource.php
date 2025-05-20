<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class StockTransferProductResource extends JsonResource
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
            'requested_quantity' => $this->requested_quantity,
            'approved_quantity' => $this->approved_quantity,
            'product' => new ProductResource($this->product),
        ];
    }
}
