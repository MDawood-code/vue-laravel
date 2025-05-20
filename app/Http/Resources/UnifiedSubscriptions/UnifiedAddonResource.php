<?php

namespace App\Http\Resources\UnifiedSubscriptions;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class UnifiedAddonResource extends JsonResource
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
            'subscription_addon_type' => 'addon',
            'name' => $this->addon?->name,
            'type' => $this->addon?->billing_cycle,
            'unit_price' => $this->price - $this->discount,
            'quantity' => null,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'created_at' => $this->created_at,
            'is_trial' => $this->trial_validity_days > 0 && (Carbon::parse($this->trial_ended_at)->isToday() || Carbon::parse($this->trial_ended_at)->isFuture()),
        ];
    }
}
