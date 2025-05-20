<?php

namespace App\Http\Requests\AdminRequest;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRequest extends FormRequest
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
            'first_name' => 'required|string|min:3',
            'last_name' => 'required|string|min:3',
            'email' => [
                'email',
                Rule::unique('users')->where(fn ($query) => $query->where('id', '!=', $this->user->id)->where('deleted_at', null)),
            ],
            'phone' => ['required',
                'string',
                'min:12',
                Rule::unique('users')->where(fn ($query) => $query->where('id', '!=', $this->user->id)->where('deleted_at', null)),
            ],
            'is_active' => 'boolean|required',
        ];
    }
}
