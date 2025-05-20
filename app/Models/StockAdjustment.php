<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['branch_id', 'date_time', 'created_by', 'reference_no', 'note'];
    /**
     * Get the stock adjustment products.
     *
     * @return HasMany<StockAdjustmentProduct>
     */
    public function stockAdjustmentProducts(): HasMany
    {
        return $this->hasMany(StockAdjustmentProduct::class);
    }

    /**
     * Get the Branch.
     *
     * @return BelongsTo<Branch, self>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the user who created it.
     *
     * @return BelongsTo<User, self>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
    protected function casts(): array
    {
        return [
            'date_time' => 'datetime',
        ];
    }
}
