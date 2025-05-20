<?php

namespace App\Enums;

enum TransactionOrderSource: int
{
    case Pos = 1;
    case Waiter = 2;
    case QrOrder = 3;
    case Kiosk = 4;

    /**
     * @return array<int|string, int>
     */
    public static function getAllValues(): array
    {
        return array_column(TransactionOrderSource::cases(), 'value');
    }
}
