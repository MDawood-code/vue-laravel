<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class RegisterUserRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<int, string|ValidationRule|Closure>|string>
     */
    public function rules(): array
    {
        $rules = [
            'first_name' => 'required|string|min:3',
            'last_name' => 'required|string|min:3',
            'email' => 'email|nullable|sometimes|unique:users',
            'password' => 'required|min:6|confirmed',
            'phone' => 'required|string|min:12|unique:users',
            'otp' => 'required|min:4',
            'preferred_contact_time' => 'nullable',
            'business_name' => [
                'required', 'string', 'min:3',
                function (string $attribute, $value, $fail): void {
                    if (request('type') == USER_TYPE_RESELLER) {
                        $fail($attribute.' is not required for resellers.');
                    }
                },
            ],
            'users_count' => 'sometimes|integer',
            'type' => 'sometimes|integer',
            'period' => 'sometimes|integer',
            'device_token' => 'sometimes|string',
            'referral_code' => 'sometimes|string',
            'reseller_number' => 'sometimes|string',
            'reseller_level' => 'sometimes|string',
        ];

        // Conditionally remove the required rule from business_name if type is USER_TYPE_RESELLER
        if (request('type') == USER_TYPE_RESELLER) {
            $rules['business_name'] = 'nullable|string|min:3';
        }

        return $rules;
    }
}
