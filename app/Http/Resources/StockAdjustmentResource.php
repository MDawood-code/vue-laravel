<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class StockAdjustmentResource extends JsonResource
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
            'branch' => new BranchResource($this->branch),
            'date_time' => $this->date_time ? $this->date_time->toISOString() : null,
            'reference_no' => $this->reference_no,
            'note' => $this->note,
            'total_quantity' => (int) $this->stock_adjustment_products_sum_quantity,
            'stocks' => StockAdjustmentProductResource::collection($this->stockAdjustmentProducts),
            'created_by' => new UserNameResource($this->createdByUser),
        ];
    }
}
