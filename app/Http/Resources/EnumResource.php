<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

class EnumResource extends JsonResource
{
    public function __construct(public $resource) {}

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
        ];
    }
}
