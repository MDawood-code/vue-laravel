<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreparePaymentCheckoutRequest extends FormRequest
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
            'brand' => [
                'required',
                Rule::in([PAYMENT_BRAND_VISA, PAYMENT_BRAND_MADA, PAYMENT_BRAND_MASTER]),
            ],
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric',
        ];
    }
}
