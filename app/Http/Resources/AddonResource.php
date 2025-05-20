<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class AddonResource extends JsonResource
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
                'name' => $this->name,
                'description' => $this->description,
                'image' => $this->image ? asset($this->image) : null,
                'icon' => $this->icon ? asset($this->icon) : null,
                'price' => $this->price,
                'discount' => $this->discount,
                'billing_cycle' => $this->billing_cycle,
                // 'trial_validity_days' => $this->getValidTrialDays(),
                'total_trial_days' => $this->when(!auth()->check(), fn () => $this->trial_validity_days),
                'trial_validity_days' => $this->when(auth()->check(), fn () => $this->getValidTrialDays()),
                'is_subscribed' => $this->whenCounted('activeCompanyAddons', fn (): bool => $this->active_company_addons_count > 0),
                'dependent_addons' => $this->collection($this->whenLoaded('dependentAddons')),
                'required_by_addons' => $this->collection($this->whenLoaded('requiredByAddons')),
            ];
     
    }

    private function getValidTrialDays(): ?int
    {
        $companyAddon = $this->companyAddons()->where('company_id', auth()->user()->company_id)->latest()->first();
        if (! $companyAddon) {
            return $this->trial_validity_days;
        }
        if ($companyAddon->trial_validity_days > 0 && (Carbon::parse($companyAddon->trial_ended_at)->isToday() || Carbon::parse($companyAddon->trial_ended_at)->isFuture())) {
            return (int)(Carbon::parse($companyAddon->trial_ended_at)->diffInDays(now(),true) + 1);
        }

        return 0;
    }
}
