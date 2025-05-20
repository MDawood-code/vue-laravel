<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockTransferProduct extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'stock_transfer_products';

    protected $fillable = ['stock_transfer_id', 'product_id', 'requested_quantity', 'approved_quantity'];

    /**
     * Get the Product.
     *
     * @return BelongsTo<Product, self>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the stock transfer.
     *
     * @return BelongsTo<StockTransfer, self>
     */
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
