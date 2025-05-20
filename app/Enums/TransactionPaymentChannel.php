<?php

namespace App\Enums;

enum TransactionPaymentChannel: string
{
    case TapToPay = 'taptopay';

    /**
     * @return array<int|string, string>
     */
    public static function getAllValues(): array
    {
        return array_column(TransactionPaymentChannel::cases(), 'value');
    }
}
