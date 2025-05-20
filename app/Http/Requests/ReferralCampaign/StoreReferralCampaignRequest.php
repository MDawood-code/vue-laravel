<?php

namespace App\Http\Requests\ReferralCampaign;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReferralCampaignRequest extends FormRequest
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
            'referral_code' => 'required|string|unique:referral_campaign',
            'referral_commission' => 'required|string',
            'expiry_date' => 'required|date|after_or_equal:today',
        ];
    }
}
