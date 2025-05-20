<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Get the Invoice.
     *
     * @return BelongsTo<Invoice, self>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * absent on Odoo scope
     *
     * @param Builder<Payment> $query
     */
    public function scopeAbsentOnOdoo(Builder $query): void
    {
        $query->where('odoo_payment_number', null)->where('updated_at', '<', now()->subMinutes(5));
    }

    /**
     * done scope
     *
     * @param Builder<Payment> $query
     */
    public function scopeDone(Builder $query): void
    {
        $query->where('status', PAYMENT_STATUS_PAID);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
