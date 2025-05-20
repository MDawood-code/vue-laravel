<?php

namespace App\Http\Requests\Stock;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Override;

class StockTransferRequest extends FormRequest
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
            'stocks' => ['required', 'array'],
            'stocks.*' => ['array'],
            'stocks.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query->where('company_id', auth()->user()->company_id)),
            ],
            'stocks.*.requested_quantity' => ['integer', 'required'],
            'branch_from_id' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', auth()->user()->company_id)),
                'different:branch_to_id',
            ],
            'branch_to_id' => [
                'required',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', auth()->user()->company_id)),
                'different:branch_from_id',
            ],
            'reference_no' => ['string'],
            'date_time' => ['required', 'date_format:Y-m-d H:i:s'], // Ensure date_time is validated
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'date_time' => now()->format('Y-m-d H:i:s'), // Automatically add the current timestamp
        ]);
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    #[Override]
    public function messages(): array
    {
        return [
            'stocks.*.product_id' => 'The selected product is invalid at :index position.',
            'stocks.*.product_id.required' => 'The product field is required at :index position.',
            'stocks.*.quantity.required' => 'The quantity field is required at :index position.',
        ];
    }
}
