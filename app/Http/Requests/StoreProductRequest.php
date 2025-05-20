<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<int, string|ValidationRule>|string>
     */
    public function rules(): array
    {
        $company_id = $this->user()?->company_id;

        return [
            'name' => [
                'required',
                'string',
                Rule::unique('products')->where(fn ($query) => $query->where('company_id', $company_id)
                    ->whereNull('deleted_at')),
            ],
            'name_en' => [
                'required',
                'max:40',
                'string',
                Rule::unique('products')->where(fn ($query) => $query->where('company_id', $company_id)
                    ->whereNull('deleted_at')),
            ],
            'price' => 'required|numeric',
            'category_id' => 'required|integer|exists:product_categories,id',
            'unit_id' => 'required|integer|exists:product_units,id',
            'odoo_reference_id' => [
                'nullable',
                'string',
            ],
            'is_qr_product' => 'nullable|boolean',
            'image' => 'nullable|image',
            'is_stockable' => 'nullable|boolean',
        ];
    }
}
