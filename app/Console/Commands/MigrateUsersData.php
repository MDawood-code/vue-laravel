<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUserLicense;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateUsersData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-users-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting the migration of user data.');
        $this->line('Adding Daily Subscription Plans...');
        SubscriptionPlan::insert([
            ['name' => 'Daily', 'type' => 3, 'period' => 3, 'price' => 0.0, 'discount' => 0.0, 'user_price' => 2.0, 'user_discount' => 0.0, 'validity_days' => 2, 'is_trial' => 0],
            ['name' => 'Daily', 'type' => 3, 'period' => 3, 'price' => 0.0, 'discount' => 0.0, 'user_price' => 0.0, 'user_discount' => 0.0, 'validity_days' => 2, 'is_trial' => 1],
        ]);
        $this->line('Daily Subscription Plans added.');
        $this->newLine();

        $this->line('Adding System Setting...');
        SystemSetting::create(['subscription_scheme' => 'daily']);
        $this->line('System Setting added.');
        $this->newLine();
        // Retrieve all companies
        $companies = Company::all();
        $bar = $this->output->createProgressBar(count($companies));
        $bar->start();
        $totalCompaniesProcessed = 0;
        try {
            DB::transaction(function () use ($companies, &$totalCompaniesProcessed, $bar): void {
                foreach ($companies as $company) {
                    $this->line("Processing company: {$company->id} - {$company->name}");
                    // Get all subscriptions for the company, ordered by created_at or start_date
                    $subscriptions = $company->subscriptions()->orderBy('created_at')->get();

                    $this->table(
                        ['ID', 'Name', 'Type', 'Period', 'Amount', 'Validity Days', 'Start Date', 'End Date', 'Status', 'Is Trial?'],
                        $subscriptions->map(fn ($subscription): array => [
                            $subscription->id,
                            $subscription->name,
                            $subscription->type,
                            $subscription->period,
                            $subscription->amount,
                            $subscription->validity_days,
                            $subscription->start_date,
                            $subscription->end_date,
                            $subscription->status ? 'Active' : 'Inactive',
                            $subscription->is_trial ? 'Yes' : 'No',
                        ])->toArray()
                    );

                    if ($subscriptions->count() > 0) {
                        // Keep the first subscription and delete the rest
                        $firstSubscription = $subscriptions->first();
                        $subscriptions->shift(); // Remove the first element

                        $idsToDelete = [];
                        // Delete remaining subscriptions and their related SubscriptionUserLicenses
                        foreach ($subscriptions as $subscription) {
                            $subscription->userLicenses()->each(function (SubscriptionUserLicense $license): void {
                                $this->line("Deleting UserLicense: {$license->id} - Quanitity: {$license->quantity}, Amount: {$license->amount}, Start Date: {$license->start_date}, End Date: {$license->end_date}");
                                $license->delete();
                            });
                            $idsToDelete[] = $subscription->id;
                            $subscription->delete();
                        }

                        $this->info('Deleted subscriptions with IDs: '.implode(', ', $idsToDelete));

                        // Update the end_date of the first subscription
                        $newEndDate = Carbon::parse($firstSubscription->start_date)->addYear()->addDay();
                        $firstSubscription->name = 'Daily';
                        $firstSubscription->type = 3;
                        $firstSubscription->period = 3;
                        $firstSubscription->amount = 0.0;
                        $firstSubscription->license_amount = 2.0;
                        $firstSubscription->license_discount = 0.0;
                        $firstSubscription->balance = 0.0;
                        $firstSubscription->is_trial = BOOLEAN_TRUE;
                        $firstSubscription->validity_days = 365;
                        $firstSubscription->end_date = $newEndDate;
                        $firstSubscription->save();
                        $firstSubscription->userLicenses()->each(function (SubscriptionUserLicense $license) use ($newEndDate): void {
                            $license->end_date = $newEndDate;
                            $license->save();
                        });

                        $this->info("Updated first subscription (ID: {$firstSubscription->id}) end_date to: {$newEndDate}");
                    }

                    // Create or update the company balance
                    $company->balance()->create();
                    $this->info('Created company balance');

                    $totalCompaniesProcessed++;
                    $bar->advance();
                }
            });
            $bar->finish();
        } catch (Exception $e) {
            $this->error('An error occurred during migration: '.$e->getMessage());
        }
        $this->info("Migration completed successfully. Total companies processed: {$totalCompaniesProcessed}");
    }
}
