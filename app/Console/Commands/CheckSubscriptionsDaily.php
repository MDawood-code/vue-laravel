<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CrmLog;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckSubscriptionsDaily extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check_subscriptions:daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and Update Subscriptions for each Company Twice Daily';

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
        // Search for companies who don't have any subscriptions end date, later than today
        $companies = Company::doesntHave('subscriptions', 'and', function ($query): void {
            $today = Carbon::now();
            $query->where(function ($q) use ($today): void {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $today->toDateString());
            })
                ->where('status', BOOLEAN_TRUE);
        })->where('status', COMPANY_STATUS_ACTIVE)
            ->get();

        // Mark them as Subscription Ended
        foreach ($companies as $company) {
            $company->status = COMPANY_STATUS_SUBSCRIPTION_ENDED;
            $company->save();

            CrmLog::create([
                'company_id' => $company->id,
                'action' => 'System changed company status to "Subscription Ended"',
            ]);
        }

        $this->info('Check Subscriptions Daily command ran successfully.');
        $this->info('Companies\' status changed to subscription ended: '.$companies->count());

        return Command::SUCCESS;
    }
}
