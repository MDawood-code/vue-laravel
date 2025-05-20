<?php

namespace App\Http\Requests\ActivityType;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;

class StoreActivityTypeRequest extends FormRequest
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
            'title' => [
                'required',
                'string',
                'min:3',
                'max:40',
                'unique:activity_types,title,NULL,id,deleted_at,NULL',
            ],
            'icon' => [
                'nullable',
                'string',
                'min:1',
                'max:60',
            ],
        ];
    }
}
