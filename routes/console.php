<?php

use App\Console\Commands\AutoRenewExpiredSubscription;
use App\Console\Commands\CheckSubscriptionsDaily;
use App\Console\Commands\DeactivateCompaniesWithUnpaidInvoices;
use App\Console\Commands\DeactivateKycCompanies;
use App\Console\Commands\DeleteInactiveDiningTables;
use App\Console\Commands\DeleteInactiveStocks;
use App\Console\Commands\GenerateUserLicenseInvoiceStartingToday;
use App\Console\Commands\SendDelayedTicketsNotifications;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command('inspire')->hourly();
Schedule::command(AutoRenewExpiredSubscription::class)->daily();
Schedule::command(CheckSubscriptionsDaily::class)->daily();
Schedule::command(DeactivateCompaniesWithUnpaidInvoices::class)->daily();
Schedule::command(GenerateUserLicenseInvoiceStartingToday::class)->daily();
Schedule::command(DeactivateKycCompanies::class)->daily();
Schedule::command(SendDelayedTicketsNotifications::class)->daily();
Schedule::command(DeleteInactiveStocks::class)->daily();
Schedule::command(DeleteInactiveDiningTables::class)->daily();
