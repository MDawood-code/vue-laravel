<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class StockTransferResource extends JsonResource
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
            'branch_from' => new BranchResource($this->branchFrom),
            'branch_to' => new BranchResource($this->branchTo),
            'date_time' => $this->date_time ? $this->date_time->toISOString() : null,
            'status' => $this->status,
            'total_requested_quantity' => (int) $this->stock_transfer_products_sum_requested_quantity,
            'total_approved_quantity' => (int) $this->stock_transfer_products_sum_approved_quantity,
            'products' => StockTransferProductResource::collection($this->stockTransferProducts),
            'created_by' => new UserNameResource($this->createdByUser),
        ];
    }
}
