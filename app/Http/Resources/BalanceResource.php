<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class BalanceResource extends JsonResource
{
    public function __construct(public $resource) {}

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
            'amount' => $this->amount,
            'company_id' => $this->company_id,
            'expiry_date' => $this->expiry_date,
            'updated_at' => $this->updated_at,
        ];
    }
}
