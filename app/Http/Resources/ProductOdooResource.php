<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Override;

/** @mixin Product **/
class ProductOdooResource extends JsonResource
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
            'name_en' => $this->name_en,
            'price' => $this->price,
            'barcode' => $this->barcode,
            'category' => $this->category->name,
            'category_ar' => $this->category->name_ar,
            'category_id' => $this->product_category_id,
            'unit' => $this->unit->name,
            'unit_ar' => $this->unit->name_ar,
            'unit_id' => $this->product_unit_id,
            'tax_id' => $this->is_taxable ? 1 : 2,
            'image' => $this->image ? asset(Storage::url($this->image)) : null,
        ];
    }
}
