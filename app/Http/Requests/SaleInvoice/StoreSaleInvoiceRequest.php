<?php

namespace App\Http\Requests\SaleInvoice;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleInvoiceRequest extends FormRequest
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
            'items' => 'required|json',
            // 'type' => 'nullable|in:'.implode(',', [TRANSACTION_TYPE_CASH, TRANSACTION_TYPE_MADA, TRANSACTION_TYPE_STC, TRANSACTION_TYPE_CREDIT, TRANSACTION_TYPE_MULTIPAYMENT]),
            'sale_invoice_status' => 'required',
            'invoice_due_date' => 'nullable',
            'payment_source' => 'nullable|string',
            'customer_id' => 'nullable|exists:customers,id',
     
        ];
    }
}
