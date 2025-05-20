<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    use LogsActivity;
    protected $fillable = [
        'item',
        'quantity',
        'amount',
        'type',
        'invoice_id',
        'odoo_product_code',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
