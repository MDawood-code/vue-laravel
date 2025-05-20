<?php

namespace App\Http\Requests\HelpdeskTicket;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use App\Models\IssueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHelpdeskTicketRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'min:3', 'max:500'],
            'attachment' => ['nullable', 'image', 'max:4096'],
            'status' => [
                'nullable',
                'integer',
                Rule::in([
                    HELPDESK_TICKET_CREATED,
                    HELPDESK_TICKET_IN_PROGRESS,
                    HELPDESK_TICKET_DONE,
                    HELPDESK_TICKET_CLOSED,
                ]),
            ],
            'issue_type_id' => [
                'nullable',
                'integer',
                Rule::in(IssueType::pluck('id')),
            ],
            'issue_comment' => ['nullable', 'string'],
        ];
    }
}
