<?php

namespace App\Http\Resources;

use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Region **/
class RegionResource extends JsonResource
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
            'name_en' => $this->name_en,
            'name_ar' => $this->name_ar,
            'country' => [
                'id' => $this->country->id,
                'name_en' => $this->country->name_en,
                'name_ar' => $this->country->name_ar,
            ],
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
