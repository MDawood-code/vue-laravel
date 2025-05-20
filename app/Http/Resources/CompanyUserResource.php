<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin User **/
class CompanyUserResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'app_config' => $this->app_config ? json_decode($this->app_config) : json_decode('{"direction":"ltr"}'),
            'company_id' => $this->company_id,
            'branch_id' => $this->branch_id,
            'branch_name' => $this->branch->name ?? 'Default',
            'is_active' => (bool) $this->is_active,
            'is_waiter' => (bool) $this->is_waiter,
            'created_at' => $this->created_at,
            'preferred_contact_time' => $this->preferred_contact_time,
        ];
    }
}
