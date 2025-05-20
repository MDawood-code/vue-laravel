<?php

namespace App\Http\Requests\DeviceRequest;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
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
            'imei' => [
                'required',
                'string',
                Rule::unique('devices')->where(fn ($query) => $query->where('id', '!=', $this->device->id)->where('deleted_at', null)),
            ],
            'serial_no' => [
                'required',
                'string',
                Rule::unique('devices')->where(fn ($query) => $query->where('id', '!=', $this->device->id)->where('deleted_at', null)),
            ],
            'warranty_starting_at' => ['date', 'nullable'],
            'warranty_ending_at' => ['date', 'after:warranty_starting_at', 'nullable'],
            // 'amount' => ['required', 'numeric', 'gte:0'],
            // 'installments' => ['required', 'integer', Rule::in([1, 2, 4, 6, 8, 10, 12])],
        ];
    }
}
