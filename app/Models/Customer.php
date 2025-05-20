<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, LogsActivity;
    protected $table = 'customers';

    // Define the fillable attributes
    protected $fillable = [
        'city_id',
        'company_id',
        'created_by',
        'country',
        'cr',
        'name_ar',
        'name_en',
        'phone',
        'postal_code',
        'state_id',
        'user_type',
        'vat',
        'street',
        'building_number',
        'plot_id_number',
    ];

    /**
     * Get the city.
     *
     * @return BelongsTo<City, self>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

     /**
     * Get the state.
     *
     * @return BelongsTo<Region, self>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /**
     * Get the transactions.
     *
     * @return HasMany<Transaction>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

     /**
     * Get the company that owns the customer.
     *
     * @return BelongsTo<Company, self>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the user who created the customer.
     *
     * @return BelongsTo<User, self>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
