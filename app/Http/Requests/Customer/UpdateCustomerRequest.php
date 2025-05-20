<?php

namespace App\Http\Requests\Customer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
        $rules = [
            'user_type' => 'required|string',
            'name_en' => 'nullable|string',
            'phone' => 'nullable|string',
        ];

        if ($this->input('user_type') === 'individual') {
            $rules['name_ar'] = 'required|string';
        } elseif ($this->input('user_type') === 'company') {
            $rules['cr'] = 'required|string';
            $rules['country'] = 'required|string';
            $rules['name_ar'] = 'required|string';
            $rules['postal_code'] = 'required|string';
            $rules['state_id'] = 'required|exists:regions,id';
            $rules['vat'] = 'required|string';
            $rules['street'] = 'required|string';
            $rules['building_number'] = 'required|string';
            $rules['plot_id_number'] = 'required|string';
            $rules['city_id'] = 'required|exists:cities,id';
        }

        return $rules;
    }
}
