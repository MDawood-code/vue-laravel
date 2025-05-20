<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionItem extends Model
{
    use HasFactory, LogsActivity;

    public $timestamps = false;

    protected $fillable = [
        'name',
        'name_en',
        'price',
        'quantity',
        'tax',
        'subtotal',
        'category',
        'unit',
        'barcode',
        'image',
        'product_id',
        'transaction_id',
        'user_id',
    ];

    /**
     * Get the Transaction.
     *
     * @return BelongsTo<Transaction, self>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Get the Product.
     *
     * @return BelongsTo<Product, self>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
