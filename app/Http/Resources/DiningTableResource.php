<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class DiningTableResource extends JsonResource
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
            'number_of_seats' => $this->number_of_seats,
            'is_drive_thru' => $this->is_drive_thru,
            'qr_code_url' => $this->getQrCodeUrl(),
            'branch' => new BranchResource($this->whenLoaded('branch')),
        ];
    }

    private function getQrCodeUrl(): ?string
    {
        if (hasActiveQrOrderingAddon($this->branch->company->employees()->first())) {
            return $this->qr_code_path ? asset($this->qr_code_path) : null;
        }

        return null;
    }
}
