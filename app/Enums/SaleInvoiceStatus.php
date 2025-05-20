<?php

namespace App\Enums;

use ValueError;

enum SaleInvoiceStatus: int
{
    case Draft = 1;
    case IssueInvoice = 2;
    case PartialPaidInvoice = 3;
    case PaidInvoice = 4;
    case Cancelled = 5;

    /**
     * @return array<int|string, int>
     */
    public static function getAllValues(): array
    {
        return array_column(SaleInvoiceStatus::cases(), 'value');
    }

    public static function fromOrDefault(mixed $value): SaleInvoiceStatus
    {
        try {
            return SaleInvoiceStatus::from($value);
        } catch (ValueError) {
            return SaleInvoiceStatus::PaidInvoice;
        }
    }
}
