<?php

namespace App\Http\Resources;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin SystemSetting **/
class SystemSettingResource extends JsonResource
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
            'subscription_scheme' => $this->subscription_scheme,
            'timezone' => $this->timezone,
        ];
    }
}
