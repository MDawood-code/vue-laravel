<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class ReferralCampaignResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray($request): array
    {
        $usedCount = Company::where('referral_code', $this->referral_code)->count();

        return [
            'id' => $this->id,
            'referral_id' => $this->referral_id,
            'referral_code' => $this->referral_code,
            'referral_commission' => $this->referral_commission,
            'expiry_date' => $this->expiry_date,
            'status' => $this->status,
            'companies' => $usedCount,
        ];
    }
}
