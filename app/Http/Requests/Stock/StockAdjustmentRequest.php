<?php

namespace App\Http\Requests\Stock;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class StockAdjustmentRequest extends FormRequest
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
            'branch_id' => ['required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', auth()->user()->company_id)),
            ],
            // 'date_time' => ['date_format:Y-m-d H:i:s'],
            'stocks' => ['required', 'array'],
            'stocks.*' => ['array'],
            'stocks.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', auth()->user()->company_id)),
            ],
            'stocks.*.quantity' => ['required', 'integer'],
            'note' => ['nullable', 'string'],
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
            'stocks.*.product_id' => 'The Seleted Product is Invalid at :index Position',
            'stocks.*.product_id.required' => 'The Product field is required at :index Position.',
            'stocks.*.quantity.required' => 'The Quantity field is required at :index Position.',
        ];
    }
}
