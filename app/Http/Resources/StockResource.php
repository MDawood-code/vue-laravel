<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class StockResource extends JsonResource
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
            'product' => new ProductResource($this->product),
            'branch' => new BranchResource($this->branch),
            'quantity' => $this->quantity,
            'created_by' => new UserNameResource($this->createdByUser),
            'branches_stock' => $this->product->stocks->map(fn ($stock): array => [
                'branch_name' => $stock->branch->name,
                'quantity' => $stock->quantity,
            ]),
        ];
    }
}
