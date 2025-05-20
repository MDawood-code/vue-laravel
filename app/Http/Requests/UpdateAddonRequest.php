<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Enums\AddonName;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAddonRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::in(AddonName::getAllValues()),
                Rule::unique('addons')->ignore($this->addon),
            ],
            'description' => 'nullable|string',
            'image' => 'nullable|image',
            'icon' =>'nullable|image' ,
            'price' => 'required|numeric',
            'discount' => 'nullable|numeric',
            'trial_validity_days' => 'nullable|integer',
        ];
    }
  
}
