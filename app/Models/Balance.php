<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Balance extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['amount', 'company_id'];

    /**
     * Get the company.
     *
     * @return BelongsTo<Company, self>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the balance deductions.
     *
     * @return HasMany<CompanyUserBalanceDeduction>
     */
    public function balanceDeductions(): HasMany
    {
        return $this->hasMany(CompanyUserBalanceDeduction::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
