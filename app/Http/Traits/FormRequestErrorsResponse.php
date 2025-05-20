<?php

namespace App\Http\Traits;

use Illuminate\Validation\ValidationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait FormRequestErrorsResponse
{
    /**
     * Handle a failed validation attempt.
     *
     *
     * @throws ValidationException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Some error occurred.',
                'data' => [
                    'errors' => $validator->getMessageBag()->toArray(),
                ],
            ], 400)
        );
    }
}
