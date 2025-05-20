<?php

namespace App\Http\Resources;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin City **/
class CityResource extends JsonResource
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
            'region' => [
                'id' => $this->region->id,
                'name_en' => $this->region->name_en,
                'name_ar' => $this->region->name_ar,
                'country' => [
                    'id' => $this->region->country->id,
                    'name_en' => $this->region->country->name_en,
                    'name_ar' => $this->region->country->name_ar,
                ],
                'is_active' => (bool) $this->region->is_active,
            ],
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
