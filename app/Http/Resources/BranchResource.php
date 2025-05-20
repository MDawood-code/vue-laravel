<?php

namespace App\Http\Resources;

use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Branch **/
class BranchResource extends JsonResource
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
            'name' => $this->name ?? '',
            'address' => $this->address ?? '',
            'code' => $this->code ?? '',
            'company' => $this->company->name,
            'company_id' => $this->company_id,
            'odoo_reference_id' => $this->odoo_reference_id,
        ];
    }
}
