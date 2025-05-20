<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'name',
        'code',
        'address',
        'company_id',
        'odoo_reference_id',
    ];

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
     * Get the employees.
     *
     * @return HasMany<User>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    /**
     * Get the discounts.
     *
     * @return BelongsToMany<Discount>
     */
    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class);
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
     * Get the dining tables.
     *
     * @return HasMany<DiningTable>
     */
    public function diningTables(): HasMany
    {
        return $this->hasMany(DiningTable::class);
    }

    public function createStocksForBranch(): void
    {
        $allProductIds = $this->company->products()->stockable()->pluck('id');
        $userId = auth()->id();

        $stocksToCreate = $allProductIds->map(fn ($productId): array => ['product_id' => $productId, 'quantity' => 0, 'created_by' => $userId])->all();
        $this->stocks()->createMany($stocksToCreate);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
