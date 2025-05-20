<?php

namespace App\Console\Commands;

use App\Enums\AddonName;
use App\Models\Addon;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Transaction;
use Illuminate\Console\Command;

class DeleteInactiveDiningTables extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addon:delete-inactive-dining-tables';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all dining tables of those companies who have unsubscribed table addon and it has been more than 3 days';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $threeDaysAgo = now()->subDays(3);
        $tableAddonId = Addon::where('name', AddonName::TableManagement->value)->first()->id;
        $companiesWithInactiveTableAddon = Company::whereHas('addons', function ($query) use ($tableAddonId, $threeDaysAgo): void {
            $query->where('addon_id', $tableAddonId)
                ->where('status', false)
                ->where('end_date', '<=', $threeDaysAgo)
                ->whereIn('id', function ($subQuery) use ($tableAddonId): void {
                    $subQuery->selectRaw('MAX(id)')
                        ->from('company_addons')
                        ->where('addon_id', $tableAddonId)
                        ->groupBy('company_id');
                });
        })->get();

        foreach ($companiesWithInactiveTableAddon as $company) {
            // First unset transactions' dining table id
            Transaction::join('dining_tables', 'transactions.dining_table_id', '=', 'dining_tables.id')
                ->join('branches', 'dining_tables.branch_id', '=', 'branches.id')
                ->where('branches.company_id', $company->id)
                ->update(['transactions.dining_table_id' => null]);

            // Delete dining tables
            $company->branches->each(function (Branch $branch): void {
                $branch->diningTables()->delete();
            });
        }

        $this->info('Inactive stocks deleted successfully.');
    }
}
