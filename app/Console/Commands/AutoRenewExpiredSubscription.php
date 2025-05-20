<?php

namespace App\Console\Commands;

use App\Events\InvoiceGenerated;
use App\Models\CrmLog;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUserLicense;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoRenewExpiredSubscription extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:renew';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command auto renews subscriptions that were expired yesterday.';

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
        $subscriptions_renewed_count = 0;
        // Get all subscriptions that were expired yesterday
        $expiredSubscriptions = Subscription::expiredYesterday()->get();
        $expiredSubscriptions->each(function ($subscription) use ($subscriptions_renewed_count): void {
            // This subscription can be an old one. So we need to make sure that it is the latest
            if ($subscription->id == $subscription->company->subscriptions()->latest()->first()->id) {
                if (getSystemSubscriptionScheme() === 'daily') {
                    $subscriptionCopy = $subscription->replicate();
                    $start_date = Carbon::now();

                    $subscriptionCopy->start_date = $start_date->toDateString();
                    $subscriptionCopy->end_date = null;
                    $subscriptionCopy->status = BOOLEAN_TRUE;
                    $subscriptionCopy->is_trial = BOOLEAN_FALSE;

                    // license amount and license_discount will be used from plan
                    // because in case of trial, the amount in previous subscription is zero
                    // also these amounts may have changed by now but will be updated in plan
                    $selected_plan = SubscriptionPlan::where('type', PLAN_TYPE_DAILY)
                        ->where('period', PERIOD_DAILY)
                        ->where('is_trial', BOOLEAN_FALSE)
                        ->first();
                    $subscriptionCopy->amount = ($selected_plan->price - ($selected_plan->price * $selected_plan->discount / 100));
                    $subscriptionCopy->license_amount = $selected_plan->user_price;
                    $subscriptionCopy->license_discount = $selected_plan->user_price * $selected_plan->user_discount / 100;
                    $subscriptionCopy->validity_days = $selected_plan->validity_days;
                    $subscriptionCopy->name = $selected_plan->name;
                    $subscriptionCopy->type = $selected_plan->type;
                    $subscriptionCopy->period = $selected_plan->period;
                    $subscriptionCopy->save();
                    // Add Subscription User Licenses if plan is pro
                    $users_count = $subscription->user_licenses_count;
                    $subscription_user_license = new SubscriptionUserLicense;
                    $subscription_user_license->quantity = $users_count;
                    $subscription_user_license->amount = 0;
                    $subscription_user_license->company_id = $subscriptionCopy->company_id;
                    $subscription_user_license->subscription_id = $subscriptionCopy->id;
                    $subscription_user_license->start_date = $start_date->toDateString();
                    $subscription_user_license->end_date = null;
                    $subscription_user_license->status = BOOLEAN_TRUE;
                    $subscription_user_license->save();
                } else {
                    $subscriptionCopy = $subscription->replicate();
                    $start_date = Carbon::now();
                    $end_date = $start_date->copy()->addDays($subscription->validity_days);
                    // If Time is Past 6 am increase 1 day
                    if ($start_date->setTimezone('Asia/Riyadh')->format('H') > 6) {
                        $end_date->addDays(1);
                    }

                    $subscriptionCopy->start_date = $start_date->toDateString();
                    $subscriptionCopy->end_date = $end_date->toDateString();
                    $subscriptionCopy->status = BOOLEAN_FALSE;
                    $subscriptionCopy->is_trial = BOOLEAN_FALSE;

                    // license amount and license_discount will be used from plan
                    // because in case of trial, the amount in previous subscription is zero
                    // also these amounts may have changed by now but will be updated in plan
                    $selected_plan = SubscriptionPlan::where('type', $subscriptionCopy->type)
                        ->where('period', $subscriptionCopy->period)
                        ->where('is_trial', BOOLEAN_FALSE)
                        ->first();
                    $subscriptionCopy->amount = $selected_plan->price - $selected_plan->discount;
                    $subscriptionCopy->license_amount = $selected_plan->user_price;
                    $subscriptionCopy->license_discount = $selected_plan->user_discount;
                    $subscriptionCopy->validity_days = $selected_plan->validity_days;
                    $subscriptionCopy->name = $selected_plan->name;
                    $subscriptionCopy->save();
                    // Add Subscription User Licenses if plan is pro
                    $users_count = $subscription->user_licenses_count;
                    if ($subscriptionCopy->type === PLAN_TYPE_PRO) {
                        $subscription_user_license = new SubscriptionUserLicense;
                        $subscription_user_license->quantity = $users_count;
                        $subscription_user_license->amount = ($subscriptionCopy->license_amount - $subscriptionCopy->license_discount) * $users_count;
                        $subscription_user_license->company_id = $subscriptionCopy->company_id;
                        $subscription_user_license->subscription_id = $subscriptionCopy->id;
                        $subscription_user_license->start_date = $start_date->toDateString();
                        $subscription_user_license->end_date = $end_date->toDateString();
                        $subscription_user_license->status = BOOLEAN_FALSE;
                        $subscription_user_license->save();
                    }

                    // Generate Invoice
                    $invoice = Invoice::generateInvoice($subscriptionCopy, INVOICE_TYPE_SUBSCRIPTION);
                    InvoiceGenerated::dispatch($invoice);
                }

                CrmLog::create([
                    'company_id' => $subscription->company->id,
                    'action' => 'System autorenewed expired subscription',
                ]);

                $subscriptions_renewed_count++;
            }
        });

        $this->info('Expired Subscriptions Renewal command ran successfully.');
        $this->info('Subscriptions Renewed: '.$subscriptions_renewed_count);

        return Command::SUCCESS;
    }
}
