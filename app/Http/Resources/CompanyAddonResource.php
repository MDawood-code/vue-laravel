<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class CompanyAddonResource extends JsonResource
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
            'name' => $this->addon?->name,
            'description' => $this->addon?->description,
            'image' => $this->addon?->image ? asset($this->addon?->image) : null,
            'price' => $this->price,
            'discount' => $this->discount,
            'billing_cycle' => $this->addon?->billing_cycle,
            'company_id' => $this->company_id,
            'addon_id' => $this->addon_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'is_trial' => $this->trial_validity_days > 0 && (Carbon::parse($this->trial_ended_at)->isToday() || Carbon::parse($this->trial_ended_at)->isFuture()),
            'trial_validity_days' => $this->trial_validity_days,
            'trial_started_at' => $this->trial_started_at,
            'trial_ended_at' => $this->trial_ended_at,
        ];
    }
}
