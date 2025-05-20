<?php

namespace App\Imports;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithValidation;
use Override;

class ProductsImport implements ToModel, WithBatchInserts, WithHeadingRow, WithLimit, WithValidation
{
    public ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * @param  array<mixed>  $row
     * @return Model|null
     */
    #[Override]
    public function model(array $row)
    {
        return new Product([
            'name_en' => $row['name_in_english'],
            'name' => $row['name_in_arabic'],
            'price' => $row['price'],
            'barcode' => null,
            'is_taxable' => $this->loggedInUser->company->is_vat_exempt ? BOOLEAN_FALSE : ($row['is_taxable'] == 'Yes' ? BOOLEAN_TRUE : BOOLEAN_FALSE),
            'product_category_id' => $this->loggedInUser->company->productCategories()->where('name', $row['category'])->first()->id,
            'product_unit_id' => $this->loggedInUser->company->productUnits()->where('name', $row['unit'])->first()->id,
            'company_id' => $this->loggedInUser->company_id,
        ]);
    }

    #[Override]
    public function limit(): int
    {
        return 301;
    }

    #[Override]
    public function batchSize(): int
    {
        return 301;
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function rules(): array
    {
        return [
            'name_in_english' => [
                'required',
                'string',
                Rule::unique('products', 'name_en')->where(fn ($query) => $query->where('company_id', $this->loggedInUser->company_id)
                    ->whereNull('deleted_at')),
            ],
            'name_in_arabic' => [
                'required',
                'max:40',
                'string',
                Rule::unique('products', 'name')->where(fn ($query) => $query->where('company_id', $this->loggedInUser->company_id)
                    ->whereNull('deleted_at')),
            ],
            'price' => 'required|numeric',
            'category' => [
                'required',
                'string',
                Rule::exists('product_categories', 'name')->where(fn (Builder $query) => $query->where('company_id', $this->loggedInUser->company_id)->whereNull('deleted_at')),
            ],
            'unit' => [
                'required',
                'string',
                Rule::exists('product_units', 'name')->where(fn (Builder $query) => $query->where('company_id', $this->loggedInUser->company_id)->whereNull('deleted_at')),
            ],
        ];
    }
}
