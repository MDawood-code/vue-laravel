<?php

namespace App\Enums;

enum AddonName: string
{
    case QrOrdering = 'QR Ordering';
    case TableManagement = 'Table Management';
    case OrderManagement = 'Order Management';
    case WaiterManagement = 'Waiter Management';
    case JobManagement = 'Job Management';
    case Stock = 'Stock';
    case TapToPay = 'TapToPay';
    case CustomerManagement = 'Customer Management';
    case A4SalesInvoice = 'A4 Sales Invoice';

    /**
     * @return array<int|string, string>
     */
    public static function getAllValues(): array
    {
        return array_column(AddonName::cases(), 'value');
    }
}
