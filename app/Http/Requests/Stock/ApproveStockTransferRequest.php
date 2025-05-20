<?php

namespace App\Http\Requests\Stock;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class ApproveStockTransferRequest extends FormRequest
{
    use FormRequestErrorsResponse;

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
            'transfer_products' => ['required', 'array'],
            'transfer_products.*' => ['array'],
            'transfer_products.*.transfer_product_id' => [
                'required',
                Rule::exists('stock_transfer_products', 'id')->where(fn ($query) => $query->where('stock_transfer_id', $this->stockTransfer?->id)),
            ],
            'transfer_products.*.approved_quantity' => ['integer', 'required'],
        ];
    }

    /**
     * Summary of messages
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'transfer_products.*.transfer_product_id' => 'The Seleted Product is Invalid at :index Position',
            'transfer_products.*.transfer_product_id.required' => 'The Product field is required at :index Position.',
            'transfer_products.*.quantity.required' => 'The Quantity field is required at :index Position.',
        ];
    }
}
