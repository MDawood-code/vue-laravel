<?php

namespace App\Rules;

use Illuminate\Translation\PotentiallyTranslatedString;
use App\Enums\TransactionStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Override;

class TransactionStatusRule implements ValidationRule
{
    public function __construct(private readonly ?TransactionStatus $currentStatus) {}

    /**
     * Run the validation rule.
     *
     * @param Closure(string):PotentiallyTranslatedString $fail
     */
    #[Override]
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value) {
            $value = (int) $value;
            $isPassed = match ($this->currentStatus->value) {
                TransactionStatus::Pending->value => in_array($value, [TransactionStatus::InProgress->value, TransactionStatus::Cancelled->value]),
                TransactionStatus::InProgress->value => in_array($value, [TransactionStatus::Completed->value, TransactionStatus::Cancelled->value]),
                default => false,
            };

            if (! $isPassed) {
                $fail('The transaction status update is not valid.');
            }
        }
    }
}
