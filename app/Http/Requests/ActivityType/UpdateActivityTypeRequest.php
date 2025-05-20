<?php

namespace App\Http\Requests\ActivityType;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActivityTypeRequest extends FormRequest
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
                Rule::unique('activity_types')->where(fn ($query) => $query->where('id', '!=', $this->activity_type->id)->where('deleted_at', null)),
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
