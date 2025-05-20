<?php

namespace App\Imports;

use Illuminate\Database\Eloquent\Model;
use App\Models\ProductCategory;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithValidation;
use Override;

class ProductCategoriesImport implements ToModel, WithBatchInserts, WithHeadingRow, WithLimit, WithValidation
{
    public ?int $company_id;

    public function __construct()
    {
        $this->company_id = auth()->guard('api')->user()->company_id;
    }

    /**
     * @param  array<mixed>  $row
     * @return Model|null
     */
    #[Override]
    public function model(array $row)
    {
        return new ProductCategory([
            'name' => $row['name_in_english'],
            'name_ar' => $row['name_in_arabic'],
            'order' => 1,
            'company_id' => $this->company_id,
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
                'max:40',
                'string',
                Rule::unique('product_categories', 'name')->where(fn ($query) => $query->where('company_id', $this->company_id)
                    ->whereNull('deleted_at')),
            ],
            'name_in_arabic' => [
                'required',
                'string',
                Rule::unique('product_categories', 'name_ar')->where(fn ($query) => $query->where('company_id', $this->company_id)
                    ->whereNull('deleted_at')),
            ],
        ];
    }
}
