<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Device **/
class DeviceResource extends JsonResource
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
            'model' => $this->model,
            'imei' => $this->imei,
            'serial_no' => $this->serial_no,
            'amount' => $this->amount,
            'due_amount' => $this->due_amount_unpaid,
            'installments' => intval($this->installments),
            'company_id' => $this->company_id,
            'warranty_starting_at' => $this->warranty_starting_at,
            'warranty_ending_at' => $this->warranty_ending_at,
        ];
    }
}
