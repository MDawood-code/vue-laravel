<?php

namespace App\Console\Commands;

use App\Enums\AddonName;
use App\Models\Addon;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Console\Command;

class DeleteInactiveStocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addon:delete-inactive-stocks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all stocks of those companies who have unsubscribed stock addon and it has been more than 3 days';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $threeDaysAgo = now()->subDays(3);
        $stockAddonId = Addon::where('name', AddonName::Stock->value)->first()->id;
        $companiesWithInactiveStockAddon = Company::whereHas('addons', function ($query) use ($stockAddonId, $threeDaysAgo): void {
            $query->where('addon_id', $stockAddonId)
                ->where('status', false)
                ->where('end_date', '<=', $threeDaysAgo)
                ->whereIn('id', function ($subQuery) use ($stockAddonId): void {
                    $subQuery->selectRaw('MAX(id)')
                        ->from('company_addons')
                        ->where('addon_id', $stockAddonId)
                        ->groupBy('company_id');
                });
        })->get();

        foreach ($companiesWithInactiveStockAddon as $company) {
            $company->branches->each(function (Branch $branch): void {
                $branch->stocks()->delete();
            });
        }

        $this->info('Inactive stocks deleted successfully.');
    }
}
