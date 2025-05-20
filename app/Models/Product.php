<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'name_en',
        'name',
        'price',
        'barcode',
        'is_taxable',
        'product_category_id',
        'product_unit_id',
        'company_id',
        'is_qr_product',
        'is_stockable',
    ];

    /**
     * Get the category.
     *
     * @return BelongsTo<ProductCategory, self>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * Get the unit.
     *
     * @return BelongsTo<ProductUnit, self>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'product_unit_id');
    }

    /**
     * Get the owner.
     *
     * @return BelongsTo<User, self>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the Company.
     *
     * @return BelongsTo<Company, self>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the stocks.
     *
     * @return HasMany<Stock>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    /**
     * Scope a query to only include stockable products.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeStockable($query)
    {
        return $query->where('is_stockable', true);
    }

    public function createStockForProduct(): void
    {
        if ($this->is_stockable) {
            $productId = $this->id;
            $userId = auth()->id();

            // Get branches that don't have stock for this product
            $branchesWithoutStock = $this->company->branches()
                ->whereDoesntHave('stocks', function ($query) use ($productId): void {
                    $query->where('product_id', $productId);
                })
                ->pluck('id');

            if ($branchesWithoutStock->isNotEmpty()) {
                $stocksToCreate = $branchesWithoutStock->map(fn ($branchId): array => [
                    'branch_id' => $branchId,
                    'quantity' => 0,
                    'product_id' => $productId,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now()
                ])->all();

                Stock::insert($stocksToCreate);
            }
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
