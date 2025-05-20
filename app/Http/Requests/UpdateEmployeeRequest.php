<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
        /** @var ?User $employee */
        $employee = $this->route('employee');
        $company = $this->user()?->company;

        return [
            'first_name' => 'required|string|min:3',
            'last_name' => 'required|string|min:3',
            'phone' => [
                'required',
                Rule::unique('users')->where(fn ($query) => $query->where('id', '!=', $employee?->id)),
            ],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($query) => $query->where('company_id', $company?->id)),
            ],
            'can_add_edit_product' => 'required',
            'can_view_sales_invoice' => $this->user() && hasActiveA4SalesInvoiceAddon($this->user()) ? 'required' : 'nullable',
            'can_add_pay_sales_invoice' => $this->user() && hasActiveA4SalesInvoiceAddon($this->user()) ? 'required' : 'nullable',
           'can_add_edit_customer' => $this->user() && hasActiveCustomerManagementAddon($this->user()) ? 'required' : 'nullable',
            'can_view_customer' => $this->user() && hasActiveCustomerManagementAddon($this->user()) ? 'required' : 'nullable',
            'can_refund_transaction' => 'required',
            'can_request_stock_adjustment' => $this->user() && hasActiveStockAddon($this->user()) ? 'required' : 'nullable',
            'allow_discount' => 'required',
            'can_see_transactions' => 'required',
            'is_waiter' => 'nullable',
            'password' => 'nullable|min:6|confirmed',
            'can_request_stock_transfer' => 'nullable',
            'can_approve_stock_transfer' => 'nullable',
        ];
    }
}
