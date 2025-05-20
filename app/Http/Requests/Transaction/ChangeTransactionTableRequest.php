<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeTransactionTableRequest extends FormRequest
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
            'dining_table_id' => [
                'nullable',
                Rule::exists('dining_tables', 'id')->where(fn ($query) => $query->where('branch_id', $this->transaction->branch_id)),
            ],
        ];
    }
}
