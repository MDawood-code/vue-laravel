<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/** @property int|null $status */
class StockTransfer extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['branch_from_id', 'branch_to_id', 'status', 'date_time', 'reference_no', 'created_by'];
    #[Override]
    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($table): void {
            $table->created_by = auth()->guard('api')->user()->id;
        });
    }

    /**
     * Get the stock transfer products.
     *
     * @return HasMany<StockTransferProduct>
     */
    public function stockTransferProducts(): HasMany
    {
        return $this->hasMany(StockTransferProduct::class, 'stock_transfer_id', 'id');
    }

    /**
     * Get the from branch.
     *
     * @return BelongsTo<Branch, self>
     */
    public function branchFrom(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_from_id');
    }

    /**
     * Get the to branch.
     *
     * @return BelongsTo<Branch, self>
     */
    public function branchTo(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_to_id');
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
