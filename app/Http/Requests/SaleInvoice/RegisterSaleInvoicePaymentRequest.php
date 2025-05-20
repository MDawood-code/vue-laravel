<?php

namespace App\Http\Requests\SaleInvoice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterSaleInvoicePaymentRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => 'required|exists:transactions,id',  
            'payment' => 'required|numeric|min:0.01', 
            'payment_method' => 'required|string', 
        ];
    }
}
