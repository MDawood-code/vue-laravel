<?php

namespace App\Enums;

use ValueError;

enum TransactionStatus: int
{
    case Pending = 1;
    case InProgress = 2;
    case Completed = 3;
    case Cancelled = 4;

    /**
     * @return array<int|string, int>
     */
    public static function getAllValues(): array
    {
        return array_column(TransactionStatus::cases(), 'value');
    }

    public static function fromOrDefault(mixed $value): TransactionStatus
    {
        try {
            return TransactionStatus::from($value);
        } catch (ValueError) {
            return TransactionStatus::Completed;
        }
    }
}
