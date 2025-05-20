<?php

namespace App\Http\Requests\Reseller;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BankDetailRequest extends FormRequest
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
            'account_title' => 'required|string',
            'bank_name' => 'required|string',
            'account_number' => 'required|string',
            'branch_code' => 'required|string',
            'city' => 'required|string',
            'country' => 'required|string',
        ];
    }
}
