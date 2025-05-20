<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CrmLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DeactivateKycCompanies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'companies:deactivate-kyc-companies';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate those companies with KYC form not completed yet within 7 days.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Log::emergency('Deactivate kyc companies older than 7 days command called.');
        $companies = Company::incompleteProfiles(7)->get();
        Log::emergency('companies: ', Company::incompleteProfiles(7)->get('id')->toArray());
        $companiesCount = Company::incompleteProfiles(7)->update(['status' => COMPANY_STATUS_BLOCKED, 'is_active' => false]);

        $companies->each(function ($company): void {
            CrmLog::create([
                'company_id' => $company->id,
                'action' => 'System deactivated company because its profile is incomplete for more than 7 days',
            ]);
        });

        $this->info('Deactivate Companies having incomplete profiles for 7 days command ran successfully.');
        $this->info($companiesCount.' companies having incomplete profiles for 7 days have been deactivated.');

        return Command::SUCCESS;
    }
}
