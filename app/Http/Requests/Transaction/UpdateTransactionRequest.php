<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use App\Enums\TransactionPaymentChannel;
use App\Http\Traits\FormRequestErrorsResponse;
use App\Rules\TransactionStatusRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransactionRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<int, string|ValidationRule|TransactionStatusRule|Enum|Exists|string>|string>
     */
    public function rules(): array
    {
        return [
            'items' => 'required|json',
            // 'type' => 'nullable|in:'.implode(',', [TRANSACTION_TYPE_CASH, TRANSACTION_TYPE_MADA, TRANSACTION_TYPE_STC, TRANSACTION_TYPE_CREDIT, TRANSACTION_TYPE_MULTIPAYMENT]),
            'type' => 'nullable',
            'payment_source' => 'nullable|string',
            'multipayments' => 'required_if:type,'.TRANSACTION_TYPE_MULTIPAYMENT.'|json',
            'discount_id' => 'nullable|exists:discounts,id',
            'cash_collected' => 'required_if:type,'.TRANSACTION_TYPE_CASH.'|numeric|min:0',
            'transaction_status' => ['nullable', new TransactionStatusRule($this->transaction?->status)],
            'reference' => 'nullable|string',
            'payment_channel' => [
                'nullable',
                'string',
                Rule::enum(TransactionPaymentChannel::class),
            ],
            'waiter_id' => [
                'nullable',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', auth()->user()->company_id)),
            ],
            'dining_table_id' => [
                'nullable',
                Rule::exists('dining_tables', 'id')->where(fn ($query) => $query->whereIn('branch_id', auth()->user()->company->branches()->pluck('id')->toArray())),
            ],
            // For drive thru currently
            'customer_name' => 'sometimes|string|max:255',
            'vehicle_number' => 'sometimes|string|max:255',
            'vehicle_color' => 'sometimes|string|max:255',
        ];
    }
}
