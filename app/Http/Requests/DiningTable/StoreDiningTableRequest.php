<?php

namespace App\Http\Requests\DiningTable;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Unique;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiningTableRequest extends FormRequest
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
     * @return array<string, array<int, ValidationRule|Unique|Closure|string>|ValidationRule|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', Rule::unique('dining_tables')->where(fn ($query) => $query->where('branch_id', $this->branch_id))],
            'number_of_seats' => ['nullable', 'integer'],
            'branch_id' => ['required', 'exists:branches,id', function ($attribute, $value, $fail): void {
                $user = auth()->user();
                if (! $user->company->branches()->where('id', $value)->exists()) {
                    $fail('The selected branch is not from the user\'s company.');
                }
            }],
            'is_drive_thru' => 'nullable',
        ];
    }
}
