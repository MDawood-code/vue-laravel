<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use App\Enums\TransactionPaymentChannel;
use App\Enums\TransactionStatus;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
     * @return array<string, array<int, Enum|Exists|string>|string>
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
            'customer_id' => 'nullable|exists:customers,id',
            'cash_collected' => 'required_if:type,'.TRANSACTION_TYPE_CASH.'|numeric|min:0',
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
            'transaction_status' => 'nullable|in:'.implode(',', [TransactionStatus::Pending->value, TransactionStatus::InProgress->value, TransactionStatus::Completed->value]),
            // For drive thru currently
            'customer_name' => 'sometimes|string|max:255',
            'vehicle_number' => 'sometimes|string|max:255',
            'vehicle_color' => 'sometimes|string|max:255',
        ];
    }
}
