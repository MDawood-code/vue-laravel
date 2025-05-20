<?php

namespace App\Http\Requests\Referral;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferralRequest extends FormRequest
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
            'first_name' => 'required|string|min:3',
            'last_name' => 'required|string|min:3',
            'email' => 'email|nullable|sometimes|unique:users',
            'password' => 'required|min:6|confirmed',
            'phone' => 'required|string|min:12|unique:users',
            'type' => 'sometimes|integer',
        ];
    }
}
