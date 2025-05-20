<?php

namespace App\Console\Commands;

use App\Events\InvoiceGenerated;
use App\Models\CrmLog;
use App\Models\Invoice;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUserLicense;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateUserLicenseInvoiceStartingToday extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoice:user-license-today';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command generates invoice for all user licenses that start from today.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get all user licenses whose start date is today
        $userLicenses = SubscriptionUserLicense::startingToday()->notDaily()->get();
        $start_date = Carbon::now();
        $userLicenses->each(function ($userLicense) use ($start_date): void {
            $validity_days = (int)$start_date->diffInDays($userLicense->end_date);
            $active_subscription = $userLicense->company->active_subscription;
            $replicated_subscription = $active_subscription->replicate();
            $related_subscription_plan = SubscriptionPlan::where('type', $active_subscription->type)
                ->where('period', $active_subscription->period)
                ->where('is_trial', BOOLEAN_FALSE)
                ->first();
            $replicated_subscription->license_amount = $related_subscription_plan->user_price;
            $replicated_subscription->license_discount = $related_subscription_plan->user_discount;
            $replicated_subscription->validity_days = $validity_days;
            $invoice = Invoice::generateInvoice($replicated_subscription, INVOICE_TYPE_LICENSE, $userLicense->quantity);
            InvoiceGenerated::dispatch($invoice);

            CrmLog::create([
                'company_id' => $userLicense->company->id,
                'action' => 'System generated invoice for user license starting from today',
            ]);
        });

        $this->info('Generate UserLicense Invoice Starting Today command ran successfully.');
        $this->info('Invoices generated: '.$userLicenses->count());

        return Command::SUCCESS;
    }
}
