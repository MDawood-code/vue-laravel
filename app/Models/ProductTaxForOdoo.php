<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Sushi\Sushi;

class ProductTaxForOdoo extends Model
{
    use HasFactory, Sushi, LogsActivity;

    /** @var array<mixed> */
    protected $rows = [
        [
            'id' => 1,
            'name' => 'VAT@15%',
            'tax_percentage' => 15,
        ],
        [
            'id' => 2,
            'name' => 'Non-Taxable',
            'tax_percentage' => 0,
        ],
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
