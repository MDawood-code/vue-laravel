<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionPlanRequest extends FormRequest
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
            'price' => $this->input('user_price') === null || $this->integer('user_price') === 0 ? 'nullable|integer|min:1' : 'nullable|integer|min:0',
            'discount' => 'nullable|integer|min:0',
            'user_price' => $this->input('price') === null || $this->integer('price') === 0 ? 'nullable|integer|min:1' : 'nullable|integer|min:0',
            'user_discount' => 'nullable|integer|min:0',
        ];
    }
}
