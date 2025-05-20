<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

class Stock extends Model
{
    use LogsActivity;
    protected $fillable = ['product_id', 'branch_id', 'quantity', 'status', 'created_by'];

    #[Override]
    public static function boot(): void
    {
        parent::boot();

        static::creating(function ($table): void {
            $table->created_by = auth()->guard('api')->user()->id;
        });
    }

    /**
     * Get the Branch.
     *
     * @return BelongsTo<Branch, self>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
}
