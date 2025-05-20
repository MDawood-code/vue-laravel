<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SaleInvoicePayment extends Model
{
    use HasFactory, LogsActivity;
    protected $fillable = [
        'transaction_id',
        'payment',
        'payment_method',
        'created_by',
    ];


   /**
     * Relationship with Transaction.
     *
     * @return BelongsTo<Transaction, SaleInvoicePayment>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Relationship with User (Created By).
     *
     * @return BelongsTo<User, SaleInvoicePayment>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
