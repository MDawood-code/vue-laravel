<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;

class ValidateRegisterRequest extends FormRequest
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
            'first_name' => 'required|string|min:3',
            'last_name' => 'required|string|min:3',
            'email' => 'email|nullable|sometimes|unique:users',
            'password' => 'required|min:6|confirmed',
            'phone' => 'required|string|min:12|unique:users',
            'preferred_contact_time' => 'nullable',
            'referral_code' => 'nullable',
            'reseller_number' => 'nullable',
            'type' => 'sometimes|integer',
            'business_name' => 'string|min:3',
        ];
    }
}
