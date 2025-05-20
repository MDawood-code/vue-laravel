<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class ResellerBankDetailResource extends JsonResource
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
            'reseller_id' => $this->reseller_id,
            'account_title' => $this->account_title,
            'bank_name' => $this->bank_name,
            'account_number' => $this->account_number,
            'branch_code' => $this->branch_code,
            'city' => $this->city,
            'country' => $this->country,
        ];
    }
}
