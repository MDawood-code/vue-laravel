<?php

namespace App\Http\Resources;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin SubscriptionPlan **/
class SubscriptionPlanResource extends JsonResource
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
            'type' => $this->type,
            'period' => $this->period,
            'price' => $this->price,
            'discount' => $this->discount,
            'user_price' => $this->user_price,
            'user_discount' => $this->user_discount,
            'validity_days' => $this->validity_days,
        ];
    }
}
