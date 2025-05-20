<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyUserBalanceDeduction extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['amount', 'deduction_type', 'company_id', 'user_id', 'balance_id', 'created_at'];

    /**
     * Get the User.
     *
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
     * Get the Balance.
     *
     * @return BelongsTo<Balance, self>
     */
    public function balance(): BelongsTo
    {
        return $this->belongsTo(Balance::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
