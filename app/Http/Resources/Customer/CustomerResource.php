<?php

namespace App\Http\Resources\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;
class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        // Base data
        $data = [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'phone' => $this->phone,
            'user_type' => $this->user_type,
        ];

        // Additional data for 'company' user_type
        if ($this->user_type === 'company') {
            $data['cr'] = $this->cr;
            $data['country'] = $this->country;
            $data['postal_code'] = $this->postal_code;
            $data['vat'] = $this->vat;
            $data['street'] = $this->street;
            $data['building_number'] = $this->building_number;
            $data['plot_id_number'] = $this->plot_id_number;
            $data['state'] = [
                'id' => $this->state->id,
                'name_ar' => $this->state->name_ar,
                'name_en' => $this->state->name_en,
            ];
            $data['city'] = [
                'id' => $this->city->id,
                'name_ar' => $this->city->name_ar,
                'name_en' => $this->city->name_en,
            ];
        }


        return $data;
    }
}
