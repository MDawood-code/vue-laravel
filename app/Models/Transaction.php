<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Enums\TransactionOrderSource;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Override;

/**
 * These properties are dynamically added to the model via `SELECT x AS y`
 * and potentially have not been set at any given time.
 *
 * @property-read ?int $refunded
 * @property-read ?int $total_sales
 * @property-read ?int $taxes
 * @property TransactionStatus|string|int|null $status
 * @property mixed $order_source
 */
class Transaction extends Model
{
    use SoftDeletes, LogsActivity;
    /**
     * The "booted" method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::deleting(function (Transaction $transaction): void {
            $transaction->items()->each(function (TransactionItem $transactionItem): void {
                $transactionItem->delete();
            });
            $transaction->multipayments()->each(function (TransactionMultipayment $transactionMultipayment): void {
                $transactionMultipayment->delete();
            });
        });
    }

    /**
     * completed scope
     *
     * @param Builder<Transaction> $query
     */
    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', TransactionStatus::Completed->value);
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
     * Get the Branch.
     *
     * @return BelongsTo<Branch, self>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the transaction items.
     *
     * @return HasMany<TransactionItem>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Get the User.
     *
     * @return BelongsTo<User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the refunded transactions.
     *
     * @return HasMany<Transaction>
     */
    public function refundTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'refunded_transaction_id', 'id');
    }

    /**
     * Get the reference transaction.
     *
     * @return BelongsTo<Transaction, self>
     */
    public function referenceTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'refunded_transaction_id', 'id');
    }

    /**
     * Get the multi payments.
     *
     * @return HasMany<TransactionMultipayment>
     */
    public function multipayments(): HasMany
    {
        return $this->hasMany(TransactionMultipayment::class);
    }

    /**
     * Get the Discount.
     *
     * @return BelongsTo<Discount, self>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * Get the dining table.
     *
     * @return BelongsTo<DiningTable, self>
     */
    public function diningTable(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class);
    }

    /**
     * Get the waiter.
     *
     * @return BelongsTo<User, self>
     */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    // public function getOrderIDAttribute(): string
    // {
    //     return $this->getOrderID($this->id);
    // }

    // public function getOrderID(int $transaction_id): string
    // {
    //     $order_id = Str::remove(' ', $this->company->name);
    //     $order_id = Str::substr($order_id, 0, 3);
    //     $order_id = Str::upper($order_id);
    //     $order_id .= '-' . Str::padLeft(strval($this->company->owner->id), 3, '0');
    //     $order_id .= '-' . Str::padLeft(strval($transaction_id), 5, '0');

    //     return $order_id;
    // }

    public function generatePDF(): ?string
    {
        $wkhtmltoPDFPath = stripos(PHP_OS, 'WIN') === 0 ? ".\wkhtmltopdf.exe" : '/usr/local/bin/wkhtmltopdf';

        $executable_path = $wkhtmltoPDFPath;
        $param = '-T 15 -B 15 -L 10 -R 10';
        $webpage_link = url('companies/'.$this->company->id.'/transactions/'.$this->id);
        $generated_pdf_path = Storage_path('app/public/transaction_slips/'.$this->uid.'.pdf');

        // Merging Command Path
        $cmd = $executable_path.' '.$param.' '.$webpage_link.' '.$generated_pdf_path;

        // Executing command
        exec($cmd);

        return $generated_pdf_path;
    }

    /**
     * Get the transaction's status using enum
     *
     * @return Attribute<TransactionStatus|null, TransactionStatus>
     */
    protected function status(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?TransactionStatus => is_null($value) ? null : TransactionStatus::from($value),
            set: fn (TransactionStatus $value) => $value->value,
        );
    }

    /**
     * Get the transaction's order source using enum.
     *
     * @return Attribute<TransactionOrderSource|null, TransactionOrderSource>
     */
    protected function orderSource(): Attribute
    {
        return Attribute::make(
            get: fn ($value): ?TransactionOrderSource => is_null($value) ? null : TransactionOrderSource::from($value),
            set: fn (TransactionOrderSource $value) => $value->value,
        );
    }

    /**
     * Get the customer.
     *
     * @return BelongsTo<Customer, self>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

        /**
     * Get the payments for the transaction.
     *
     * @return HasMany<SaleInvoicePayment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SaleInvoicePayment::class, 'transaction_id');
    }
        /**
     * Calculate the balance for this transaction.
     */
    public function calculateBalance(): float
    {
        $totalPayments = $this->payments()->sum('payment'); 
        return $this->amount_charged - $totalPayments;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
    protected function casts(): array
    {
        return [
            'invoice_due_date' => 'datetime',
            'create_invoice_date' => 'datetime',
        ];
    }

}
