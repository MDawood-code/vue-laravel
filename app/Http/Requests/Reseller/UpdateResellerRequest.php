<?php

namespace App\Http\Requests\Reseller;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateResellerRequest extends FormRequest
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
        /** @var ?User $reseller */
        $reseller = $this->route('reseller');
        $resellerId = $reseller?->id;

        return [
            'first_name' => 'required|string|min:3',
            'last_name' => 'required|string|min:3',
            'email' => [
                'email',
                Rule::unique('users')->where(fn ($query) => $query->where('id', '!=', $resellerId)->where('deleted_at', null)),
            ],
            'phone' => [
                'required',
                'string',
                'min:12',
                Rule::unique('users')->where(fn ($query) => $query->where('id', '!=', $resellerId)->where('deleted_at', null)),
            ],
            'user_photo_id' => 'nullable|image',
            'reseller_company_name' => 'nullable|string',
            'company_registration_document' => 'nullable|file|mimes:pdf,doc,docx',
            'user_type' => 'nullable|string',
        ];
    }
}
