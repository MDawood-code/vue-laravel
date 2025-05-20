<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Device extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'model',
        'imei',
        'serial_no',
        'company_id',
        'amount',
        'installments',
        'warranty_starting_at',
        'warranty_ending_at',
    ];

    // This returns amount of device for which there is no invoice.
    // It does not cover paid/unpaid invoice scenario.
    public function getDueAmountAttribute(): float|int
    {
        return $this->attributes['amount'] - DB::table('devices_invoices')
            ->where('device_id', $this->attributes['id'])
            ->sum('amount');
    }

    // This returns amount of device for which there is no invoice or invoice is not paid.
    // It covers paid/unpaid invoice scenario as well.
    public function getDueAmountUnpaidAttribute(): float|int
    {
        $paid_invoices_ids = $this->invoices()->paid()->pluck('invoices.id');
        if ($paid_invoices_ids->isEmpty()) {
            return $this->attributes['amount'];
        }

        return $this->attributes['amount'] - DB::table('devices_invoices')
            ->where('device_id', $this->attributes['id'])
            ->whereIn('invoice_id', $paid_invoices_ids)
            ->sum('amount');
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
     * Get the invoices.
     *
     * @return BelongsToMany<Invoice>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'devices_invoices');
    }

    /**
     * Summary of invoiceDetails
     *
     * @return Collection<int, InvoiceDetail>
     */
    public function invoiceDetails(): ?Collection
    {
        // return $this->hasManyThrough(InvoiceDetail::class, Invoice::class);
        // return $this->invoices->details();
        $invoiceDetails = collect();
        $this->invoices->each(function ($invoice) use ($invoiceDetails): void {
            $invoice->details->each(function ($detail) use ($invoiceDetails): void {
                $invoiceDetails->push($detail);
            });
        });

        return $invoiceDetails;
    }

    /**
     * Check if the device payment is completed
     */
    public function isPaymentComplete(): bool
    {
        $amountPaid = DB::table('devices_invoices')
            ->where('device_id', $this->id)
            ->sum('amount');

        return $this->amount <= $amountPaid;
    }

    /**
     * Check if the last invoice was generated in this month
     */
    public function isLastInvoiceThisMonth(): bool
    {
        return $this->invoices()->latest()->first()->created_at->isAfter(now()->subMonthsNoOverflow()->endOfMonth());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
