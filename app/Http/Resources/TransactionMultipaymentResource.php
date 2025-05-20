<?php

namespace App\Http\Resources;

use App\Models\TransactionMultipayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin TransactionMultipayment **/
class TransactionMultipaymentResource extends JsonResource
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
            'transaction_type' => getTransactionTypeText($this->transaction_type),
            'amount' => $this->amount,
        ];
    }
}
