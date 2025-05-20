<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class SingleReferralResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'campaigns' => $this->whenLoaded('referralCampaigns', fn () => ReferralCampaignResource::collection($this->referralCampaigns)) ?? null,
        ];
    }
}
