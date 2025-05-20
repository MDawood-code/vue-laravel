<?php

namespace App\Http\Requests\Discount;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
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
            'discount_percentage' => 'required|between:0,99.99',
            'branches.*' => [
                'required',
                Rule::exists('branches', 'id')->where(fn (Builder $query) => $query->where('company_id', auth()->guard('api')->user()->company_id)),
            ],
        ];
    }
}
