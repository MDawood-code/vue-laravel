<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use App\Models\City;
use App\Models\Region;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminStaffRequest extends FormRequest
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
            'email' => 'email|unique:users',
            'password' => 'required|min:6',
            'phone' => 'required|string|min:12|unique:users',
            'is_active' => 'boolean|required',
            'is_support_agent' => 'boolean|required',
            'can_manage_all_regions' => 'boolean|required',
            'cities' => [
                'required',
                'array',
            ],
            'cities.*' => Rule::in(City::pluck('id')),
            'region_id' => ['required', Rule::in(Region::pluck('id'))],
        ];
    }
}
