<?php

namespace App\Http\Requests\Reseller;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ChangeStatusRequest extends FormRequest
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
        return [
            'status' => 'required|integer',
            'rejection_reason' => [
                'required_if:status,'.RESELLER_STATUS_REJECTED,
                function ($attribute, $value, $fail): void {
                    if ($this->input('status') == RESELLER_STATUS_REJECTED && empty($value)) {
                        $fail('The rejection reason is required');
                    }
                },
            ],
        ];
    }
}
