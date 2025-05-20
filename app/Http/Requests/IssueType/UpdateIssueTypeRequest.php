<?php

namespace App\Http\Requests\IssueType;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIssueTypeRequest extends FormRequest
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
            'type' => [
                'required',
                'string',
                'min:3',
                'max:200',
                Rule::unique('issue_types')->where(fn ($query) => $query->where('id', '!=', $this->issue_type)),
            ],
        ];
    }
}
