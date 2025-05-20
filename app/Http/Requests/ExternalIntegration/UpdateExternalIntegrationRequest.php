<?php

namespace App\Http\Requests\ExternalIntegration;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExternalIntegrationRequest extends FormRequest
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
            'url' => [
                'required',
                'url',
            ],
            'secret_key' => 'required',
            'external_integration_type_id' => [
                'integer',
                Rule::exists('external_integration_types', 'id'),
            ],
        ];
    }
}
