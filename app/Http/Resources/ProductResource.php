<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Override;

/** @mixin Product **/
class ProductResource extends JsonResource
{
    protected ?int $branchId = null;
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $src = request()->query('src');
        $this->branchId = is_string($src) ? Crypt::decrypt($src) : auth()->user()?->branch_id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'price' => $this->price,
            'stock' => $this->stocks->where('branch_id', $this->branchId)->first()?->quantity,
            'barcode' => $this->barcode,
            'category' => $this->category->name,
            'category_ar' => $this->category->name_ar,
            'category_id' => $this->product_category_id,
            'unit' => $this->unit->name,
            'unit_ar' => $this->unit->name_ar,
            'unit_id' => $this->product_unit_id,
            'is_taxable' => (bool) $this->is_taxable,
            'image' => $this->image ? asset(Storage::url($this->image)) : null,
            'odoo_reference_id' => $this->odoo_reference_id,
            'is_qr_product' => (bool) $this->is_qr_product,
            'is_stockable' => (bool) $this->is_stockable,
        ];
    }
}
