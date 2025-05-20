<?php

namespace App\Http\Requests\Reseller;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResellerLevelConfigurationRequest extends FormRequest
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
            'basic_commission' => 'required|numeric',
            'basic_retain_rate' => 'required|numeric',
            'basic_target' => 'required|numeric',
            'pro_commission' => 'required|numeric',
            'pro_retain_rate' => 'required|numeric',
            'pro_target' => 'required|numeric',
        ];
    }
}
