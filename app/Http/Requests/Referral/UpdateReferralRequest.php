<?php

namespace App\Http\Requests\Referral;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferralRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ?User $referral */
        $referral = $this->route('referral');
        $referralId = $referral?->id;

        return [
            'first_name' => 'required|string|min:3',
            'last_name' => 'required|string|min:3',
            'email' => [
                'email',
                Rule::unique('users')->where(fn ($query) => $query->where('id', '!=', $referralId)->where('deleted_at', null)),
            ],
            'phone' => [
                'required',
                'string',
                'min:12',
                Rule::unique('users')->where(fn ($query) => $query->where('id', '!=', $referralId)->where('deleted_at', null)),
            ],
            'is_active' => 'boolean|required',
        ];
    }
}
