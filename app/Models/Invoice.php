<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Override;

class Invoice extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'uid',
        'status',
        'subscription_id',
        'odoo_uid',
    ];

    public static function generateInvoiceUID(): string
    {
        $invoice = Invoice::orderByDesc('uid')->first();
        $last_uid = 0;
        if ($invoice) {
            $temp = explode('-', $invoice->uid);
            $last_uid = (int) $temp[count($temp) - 1];
        }

        // Initially it was INV- but due to using invoice from Odoo, we are supposed to have one such id
        // return 'INV-' . Str::padLeft(strval($last_uid + 1), 5, '0');
        return 'ORD-'.Str::padLeft(strval($last_uid + 1), 5, '0');
    }

    public static function generateInvoice(Subscription $subscription, int $type = INVOICE_TYPE_SUBSCRIPTION, int $license_count = 0): Invoice
    {
        $license_amount = 0;
        $license_discount = 0;
        $license_item_detail = '';
        $plan_amount = 0;
        $odoo_user_license_product_reference = null;
        if ($type === INVOICE_TYPE_SUBSCRIPTION) {
            $plan_amount = $subscription->amount;
            if ($subscription->type === PLAN_TYPE_PRO) {
                $license_count = $license_count ?: $subscription->user_licenses_count_all;
                $license_item_detail = $subscription->period === PERIOD_MONTHLY
                    ? 'User License (1 Month)'
                    : 'User License (1 Year)';
                $license_amount = $subscription->license_amount;
                $license_discount = $subscription->license_discount;
                $odoo_user_license_product_reference = $subscription->period === PERIOD_MONTHLY ? ODOO_MONTHLY_SUB : ODOO_ANNUAL_SUB;
            } elseif ($subscription->type === PLAN_TYPE_DAILY) {
                $license_count = $license_count ?: $subscription->user_licenses_count_all;
                $license_item_detail = 'User License (Daily)';
                // license values are 0 because the balance has all the amount user has to pay for daily amount
                $license_amount = 0;
                $license_discount = 0;
                // TODO: This should be set to ODOO_DAILY_SUB but after it is configured on Odoo
                // $odoo_user_license_product_reference = $subscription->period === PERIOD_MONTHLY ? ODOO_MONTHLY_SUB : ODOO_ANNUAL_SUB;
            }
        } elseif ($type === INVOICE_TYPE_LICENSE) {
            if ($subscription->period === PERIOD_MONTHLY) {
                $license_amount = $subscription->license_amount;
                $license_discount = $subscription->license_discount;
                $license_item_detail = 'User License (1 Month)';
                $odoo_user_license_product_reference = ODOO_MONTHLY_SUB;
            } elseif ($subscription->period === PERIOD_YEARLY) {
                // Calculate Yearly Amount
                $end_date = Carbon::createFromFormat('Y-m-d', $subscription->end_date);
                throw_if($end_date == false, new Exception("Invalid date format: {$subscription->end_date}"));
                $end_date = $end_date->endOfDay();
                $subscription_ending_in_days = Carbon::now()->startOfDay()->diffInDays($end_date);

                if ($subscription_ending_in_days >= 330) {
                    $license_amount = $subscription->license_amount;
                    $license_discount = $subscription->license_discount;
                    $license_item_detail = 'User License (1 Year)';
                    $odoo_user_license_product_reference = ODOO_ANNUAL_SUB;
                } else {
                    $months = ceil($subscription_ending_in_days / 30);
                    // Initially it was like this. But it seems wrong
                    // $license_amount = $subscription->license_amount * $months;
                    $license_amount = $subscription->license_amount / 12 * $months;
                    $license_discount = 0;
                    $license_item_detail = 'Users License ('.$months.' Month(s))';
                    $odoo_user_license_product_reference = ODOO_BALANCE_MONTHS_SUB;
                }
            }
        }

        $license_total_amount = $license_amount * $license_count;
        $license_total_discount = $license_discount * $license_count;
        $license_total_amount_after_discount = $license_total_amount - $license_total_discount;
        if ($subscription->type === PLAN_TYPE_DAILY) {
            $total_amount = $subscription->balance;
        } else {
            $total_amount = $plan_amount + $license_total_amount_after_discount;
        }
        $vat_amount = $total_amount * TAX_PERCENTAGE;

        // Creating a New Invoice
        $invoice = new Invoice;
        if ($type === INVOICE_TYPE_SUBSCRIPTION) {
            $invoice->uid = self::generateInvoiceUID();
            $invoice->type = INVOICE_TYPE_SUBSCRIPTION;
        } else {
            $invoice->type = INVOICE_TYPE_LICENSE;
        }
        $invoice->amount_charged = $total_amount + $vat_amount;
        $invoice->status = INVOICE_STATUS_UNPAID;
        $invoice->subscription_id = $subscription->id;
        $invoice->company_id = $subscription->company_id;
        $invoice->save();

        // Add Plan Details to Invoice
        if ($type === INVOICE_TYPE_SUBSCRIPTION) {
            // Add Plan Amount
            $invoiceDetail = new InvoiceDetail;
            $invoiceDetail->item = $subscription->name;
            $invoiceDetail->quantity = 1;
            $invoiceDetail->amount = $subscription->type === PLAN_TYPE_DAILY ? $subscription->balance : $subscription->amount;
            $invoiceDetail->type = INVOICE_DETAIL_TYPE_SUBSCRIPTION;
            $invoiceDetail->invoice_id = $invoice->id;
            $invoiceDetail->save();
        }

        if ($license_total_amount > 0) {
            // Add User Amount
            $invoiceDetail = new InvoiceDetail;
            $invoiceDetail->item = $license_item_detail;
            $invoiceDetail->quantity = $license_count;
            $invoiceDetail->amount = $license_total_amount;
            $invoiceDetail->type = INVOICE_DETAIL_TYPE_LICENSE;
            $invoiceDetail->invoice_id = $invoice->id;
            $invoiceDetail->odoo_product_code = $odoo_user_license_product_reference;
            $invoiceDetail->save();
        }

        if ($subscription->period === PERIOD_YEARLY && $license_total_discount > 0) {
            // Add Discount
            $invoiceDetail = new InvoiceDetail;
            $invoiceDetail->item = 'Yearly Discount';
            $invoiceDetail->quantity = 1;
            $invoiceDetail->amount = $license_total_discount * -1;
            $invoiceDetail->type = INVOICE_DETAIL_TYPE_DISCOUNT;
            $invoiceDetail->invoice_id = $invoice->id;
            $invoiceDetail->save();
        }

        if ($type === INVOICE_TYPE_SUBSCRIPTION) {
            // Update Subscription Status
            $subscription->status = BOOLEAN_TRUE;
            $subscription->save();

            // Update Company Status
            $subscription->company->status = COMPANY_STATUS_SUBSCRIPTION_INVOICE_GENERATED;
            $subscription->company->save();
        }

        // 15% VAT
        $invoiceDetail = new InvoiceDetail;
        $invoiceDetail->item = 'VAT (15%)';
        $invoiceDetail->quantity = 1;
        $invoiceDetail->amount = $vat_amount;
        $invoiceDetail->type = INVOICE_DETAIL_TYPE_TAX;
        $invoiceDetail->invoice_id = $invoice->id;
        $invoiceDetail->save();

        //TODO: Add Custom Discount

        return $invoice;
    }

    /**
     * Summary of generateDevicePaymentInvoice
     *
     * @param Collection<int, Device> $devices
     */
    public static function generateDevicePaymentInvoice(Collection $devices, int $subscriptionId): Invoice
    {
        // Generate Invoice

        // calculate each device paid amount.
        // device installment amount will be calculated by dividing device amount by installments.
        // if amount is in fraction, round off to nearest integer.
        //
        $amount = 0;
        $devices_installments = [];
        $devices->each(function ($device) use (&$amount, &$devices_installments): void {
            $due_amount = $device->due_amount;
            $installment_amount = $device->installments == 1 ? $device->amount : floor($device->amount / $device->installments);
            if ($due_amount <= $installment_amount) {
                $installment_amount = $due_amount;
            }
            $amount += $installment_amount;
            // store device id as key and installment of this device
            $devices_installments[$device->id] = $installment_amount;
        });

        $invoice = new Invoice;
        $invoice->uid = self::generateInvoiceUID();
        $invoice->type = INVOICE_TYPE_DEVICE_PAYMENT;
        $invoice->amount_charged = $amount + $amount * TAX_PERCENTAGE;
        $invoice->status = INVOICE_STATUS_UNPAID;
        $invoice->subscription_id = $subscriptionId;
        $invoice->company_id = $devices->first()->company_id;
        $invoice->save();

        $devices->each(function ($device) use ($invoice, $devices_installments): void {
            // Generate Invoice Installment Details
            $invoiceDetail = new InvoiceDetail;
            $invoiceDetail->item = $device->installments == 1 ? 'Device One Time Payment' : 'Device Installment';
            $invoiceDetail->quantity = 1;
            $invoiceDetail->amount = $devices_installments[$device->id];
            $invoiceDetail->type = INVOICE_DETAIL_TYPE_DEVICE_PAYMENT;
            $invoiceDetail->invoice_id = $invoice->id;
            $invoiceDetail->odoo_product_code = $device->installments == 1 ? ODOO_PDA_WISEASY : ODOO_PDA_WISEASY_INSTALLMENT;
            $invoiceDetail->save();

            $device->invoices()->attach($invoice->id, ['amount' => $devices_installments[$device->id]]);
        });

        // Generate Invoice Installment Details
        // $invoiceDetail = new InvoiceDetail();
        // $invoiceDetail->item = 'Device Installment';
        // $invoiceDetail->quantity = $devices->count();
        // $invoiceDetail->amount = $amount;
        // $invoiceDetail->type = INVOICE_DETAIL_TYPE_DEVICE_PAYMENT;
        // $invoiceDetail->invoice_id = $invoice->id;
        // $invoiceDetail->save();

        // Generate Invoice Installment Tax Details
        $vat_amount = $amount * TAX_PERCENTAGE;
        // 15% VAT
        $invoiceDetail = new InvoiceDetail;
        $invoiceDetail->item = 'VAT (15%)';
        $invoiceDetail->quantity = 1;
        $invoiceDetail->amount = $vat_amount;
        $invoiceDetail->type = INVOICE_DETAIL_TYPE_TAX;
        $invoiceDetail->invoice_id = $invoice->id;
        $invoiceDetail->save();

        return $invoice;
    }

    public static function generateAddonInvoice(CompanyAddon $companyAddon, Company $company): Invoice
    {
        $addons_count = 1;
        $addon_amount = $companyAddon->price;
        $discount = $companyAddon->discount;

        $total_amount = $addon_amount - $discount;
        $vat_amount = $total_amount * TAX_PERCENTAGE;

        // Creating a New Invoice
        $invoice = new Invoice;
        $invoice->type = INVOICE_TYPE_ADDON;
        $invoice->amount_charged = $total_amount + $vat_amount;
        $invoice->status = INVOICE_STATUS_UNPAID;
        $invoice->subscription_id = null;
        $invoice->company_addon_id = $companyAddon->id;
        $invoice->company_id = $company->id;
        $invoice->save();

        // Add Addon Details
        $invoiceDetail = new InvoiceDetail;
        $invoiceDetail->item = $companyAddon->addon?->name.' (Addon)';
        $invoiceDetail->quantity = $addons_count;
        $invoiceDetail->amount = $addon_amount;
        $invoiceDetail->type = INVOICE_DETAIL_TYPE_ADDON;
        $invoiceDetail->invoice_id = $invoice->id;
        // This will be set after discussion with Odoo Developer
        // $invoiceDetail->odoo_product_code = $odoo_user_license_product_reference;
        $invoiceDetail->save();

        if ($discount > 0) {
            // Add Discount
            $invoiceDetail = new InvoiceDetail;
            $invoiceDetail->item = 'Addon Discount';
            $invoiceDetail->quantity = 1;
            $invoiceDetail->amount = $discount * -1;
            $invoiceDetail->type = INVOICE_DETAIL_TYPE_DISCOUNT;
            $invoiceDetail->invoice_id = $invoice->id;
            $invoiceDetail->save();
        }

        // 15% VAT
        $invoiceDetail = new InvoiceDetail;
        $invoiceDetail->item = 'VAT (15%)';
        $invoiceDetail->quantity = 1;
        $invoiceDetail->amount = $vat_amount;
        $invoiceDetail->type = INVOICE_DETAIL_TYPE_TAX;
        $invoiceDetail->invoice_id = $invoice->id;
        $invoiceDetail->save();

        //TODO: Add Custom Discount

        return $invoice;
    }

    public static function generateTopUpBalanceInvoice(float $amount, int $companyId): ?Invoice
    {
        $vat_amount = $amount * TAX_PERCENTAGE;
        $total_amount = $amount + $vat_amount;

        // Create an invoice for the top-up amount
        $invoice = new Invoice;
        $invoice->company_id = $companyId;
        $invoice->amount_charged = $total_amount;
        $invoice->status = INVOICE_STATUS_UNPAID;
        $invoice->type = INVOICE_TYPE_BALANCE_TOPUP;
        $invoice->subscription_id = null;
        $invoice->company_addon_id = null;
        $invoice->save();

        // Add Addon Details
        $invoiceDetail = new InvoiceDetail;
        $invoiceDetail->item = 'Balance Top-Up';
        $invoiceDetail->quantity = 1;
        $invoiceDetail->amount = $amount;
        $invoiceDetail->type = INVOICE_DETAIL_TYPE_BALANCE_TOPUP;
        $invoiceDetail->invoice_id = $invoice->id;
        // This will be set after discussion with Odoo Developer
        // $invoiceDetail->odoo_product_code = $odoo_user_license_product_reference;
        $invoiceDetail->save();

        // 15% VAT
        $invoiceDetail = new InvoiceDetail;
        $invoiceDetail->item = 'VAT (15%)';
        $invoiceDetail->quantity = 1;
        $invoiceDetail->amount = $vat_amount;
        $invoiceDetail->type = INVOICE_DETAIL_TYPE_TAX;
        $invoiceDetail->invoice_id = $invoice->id;
        $invoiceDetail->save();

        return $invoice;
    }

    /**
     * Calculate the amount to be paid by user credit card
     */
    public static function calculateTopupBalanceInvoiceAmount(float $amount): float
    {
        $vat_amount = $amount * TAX_PERCENTAGE;

        return $amount + $vat_amount;
    }

    /**
     * The "booted" method of the model.
     */
    #[Override]
    protected static function booted(): void
    {
        static::deleting(function (Invoice $invoice): void {
            $invoice->details()->each(function (InvoiceDetail $invoiceDetail): void {
                $invoiceDetail->delete();
            });
        });
    }

    /**
     * Get the details.
     *
     * @return HasMany<InvoiceDetail>
     */
    public function details(): HasMany
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    /**
     * Get the Subscription.
     *
     * @return BelongsTo<Subscription, self>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
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
     * Get the payments.
     *
     * @return HasMany<Payment>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the devices.
     *
     * @return BelongsToMany<Device>
     */
    public function devices(): BelongsToMany
    {
        return $this->belongsToMany(Device::class, 'devices_invoices');
    }

    /**
     * paid scope
     *
     * @param Builder<Invoice> $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', INVOICE_STATUS_PAID);
    }

    /**
     * unpaid scope
     *
     * @param Builder<Invoice> $query
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('status', INVOICE_STATUS_UNPAID);
    }

    /**
     * absent on Odoo scope
     *
     * @param Builder<Invoice> $query
     */
    public function scopeAbsentOnOdoo(Builder $query): void
    {
        $query->where('odoo_uid', null)->where('updated_at', '<', now()->subMinutes(5));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
