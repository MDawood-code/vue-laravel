<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class ResellerLevelConfigurationResource extends JsonResource
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
            'basic_commission' => $this->basic_commission,
            'basic_retain_rate' => $this->basic_retain_rate,
            'basic_target' => $this->basic_target,
            'pro_commission' => $this->pro_commission,
            'pro_retain_rate' => $this->pro_retain_rate,
            'pro_target' => $this->pro_target,
        ];
    }
}
