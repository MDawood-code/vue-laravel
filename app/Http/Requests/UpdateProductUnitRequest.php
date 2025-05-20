<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductUnitRequest extends FormRequest
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
        return [
            'name' => [
                'required',
                'max:40',
                'string',
                Rule::unique('product_units')->where(fn ($query) => $query->where('company_id', $this->product_unit?->company_id)
                    ->where('id', '!=', $this->product_unit?->id)
                    ->whereNull('deleted_at')),
            ],
            'name_ar' => [
                'required',
                'string',
                Rule::unique('product_units')->where(fn ($query) => $query->where('company_id', $this->product_unit?->company_id)
                    ->where('id', '!=', $this->product_unit?->id)
                    ->whereNull('deleted_at')),
            ],
            'order' => 'nullable|integer',
            'odoo_reference_id' => [
                'nullable',
                'string',
            ],
        ];
    }
}
