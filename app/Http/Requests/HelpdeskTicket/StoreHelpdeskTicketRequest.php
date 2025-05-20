<?php

namespace App\Http\Requests\HelpdeskTicket;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;

class StoreHelpdeskTicketRequest extends FormRequest
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
            'description' => ['required', 'min:3', 'max:500'],
            'attachment' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
