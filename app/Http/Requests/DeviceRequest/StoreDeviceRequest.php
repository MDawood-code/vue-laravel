<?php

namespace App\Http\Requests\DeviceRequest;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDeviceRequest extends FormRequest
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
            'model' => ['required', 'string', 'min:2'],
            'imei' => ['required', 'string', 'unique:devices,imei,NULL,id,deleted_at,NULL'],
            'serial_no' => ['required', 'string', 'unique:devices,serial_no,NULL,id,deleted_at,NULL'],
            'warranty_starting_at' => ['date', 'nullable'],
            'warranty_ending_at' => ['date', 'after:warranty_starting_at', 'nullable'],
            'amount' => ['required', 'numeric', 'gte:0'],
            'installments' => ['required', 'integer', Rule::in([1, 2, 4, 6, 8, 10, 12])],
        ];
    }
}
