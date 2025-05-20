<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class CompanyUserBalanceDeductionResource extends JsonResource
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
            'deduction_type' => $this->deduction_type,
            'company_id' => $this->company_id,
            'user' => new UserNameResource($this->user),
            'balance_id' => $this->balance_id,
            'created_at' => $this->created_at,
        ];
    }
}
