<?php

namespace App\Http\Requests\Reseller;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreResellerRequest extends FormRequest
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
        $rules = [
            'first_name' => 'required|string|min:3',
            'last_name' => 'required|string|min:3',
            'email' => 'email|nullable|sometimes|unique:users',
            'password' => 'required|min:6|confirmed',
            'phone' => 'required|string|min:12|unique:users',
            'type' => 'sometimes|integer',
            'user_photo_id' => 'nullable|image',
            'reseller_company_name' => 'nullable|string',
            'company_registration_document' => 'nullable|file|mimes:pdf,doc,docx,jpeg,png,jpg',
            'user_type' => 'nullable|string',
        ];

        // Conditionally remove the rule for company_registration_document if user_type is 'individual'
        if (request('user_type') === 'individual') {
            unset($rules['company_registration_document']);
        }

        return $rules;
    }
}
