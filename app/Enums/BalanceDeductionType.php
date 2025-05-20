<?php

namespace App\Enums;

enum BalanceDeductionType: int
{
    case Subscription = 1;
    case QrOrdering = 2;
    case TableManagement = 3;
    case OrderManagement = 4;
    case WaiterManagement = 5;
    case JobManagement = 6;
    case Stock = 7;
    case TapToPay = 8;
    case CustomerManagement = 9;
    case A4SalesInvoice = 10;

    /**
     * @return array<int|string, int>
     */
    public static function getAllValues(): array
    {
        return array_column(BalanceDeductionType::cases(), 'value');
    }
}
