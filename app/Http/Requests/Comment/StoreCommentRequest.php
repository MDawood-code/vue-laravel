<?php

namespace App\Http\Requests\Comment;

use Illuminate\Contracts\Validation\ValidationRule;
use App\Http\Traits\FormRequestErrorsResponse;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
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
            'description' => [
                'required',
                'string',
                'min:1',
                'max:1024',
            ],
            'activity_id' => [
                'required_if:note_id,null',
                'integer',
            ],
            'note_id' => [
                'required_if:activity_id,null',
                'integer',
            ],
        ];
    }
}
