<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CrmLog;
use Illuminate\Console\Command;

class DeactivateCompaniesWithUnpaidInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'companies:deactivate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate those companies who have invoices generated before 7 days ago and have not paid yet.';

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
        $companies = Company::withUnpaidInvoices(7)
            ->whereIn('status', [COMPANY_STATUS_ACTIVE, COMPANY_STATUS_SUBSCRIPTION_INVOICE_GENERATED])->get();

        $companiesCount = Company::withUnpaidInvoices(7)
            ->whereIn('status', [COMPANY_STATUS_ACTIVE, COMPANY_STATUS_SUBSCRIPTION_INVOICE_GENERATED])
            ->update(['status' => COMPANY_STATUS_BLOCKED, 'is_active' => false]);

        $companies->each(function ($company): void {
            CrmLog::create([
                'company_id' => $company->id,
                'action' => 'System deactivated company because its invoice was unpaid for more than 7 days',
            ]);
        });

        $this->info('Deactivate Companies With Unpaid Invoices command ran successfully.');
        $this->info($companiesCount.' companies with unpaid invoices have been deactivated.');

        return Command::SUCCESS;
    }
}
