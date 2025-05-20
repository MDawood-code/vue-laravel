<?php

namespace App\Http\Resources\UnifiedSubscriptions;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class UnifiedSubscriptionResource extends JsonResource
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
            'subscription_addon_type' => 'subscription',
            'name' => $this->name,
            'type' => $this->type,
            'unit_price' => ($this->amount / $this->company->activeEmployees()->count()) + ($this->license_amount - $this->license_discount),
            'quantity' => $this->company->activeEmployees()->count(),
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'created_at' => $this->created_at,
            'is_trial' => (bool) $this->is_trial,
        ];
    }
}
