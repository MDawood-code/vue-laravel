<?php

namespace App\Http\Resources;

use App\Models\Company;
use App\Models\ResellerLevelConfiguration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Override;

class ResellerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $userPhotoPath = $this->user_photo_id ? $this->getPublicStorageUrl($this->user_photo_id) : null;
        $companyRegistrationDocPath = $this->company_registration_document ? $this->getPublicStorageUrl($this->company_registration_document) : null;
        $resellerNumber = $this->reseller_number;
        $trialCustomersCount = 0;
        $paidCustomersCount = 0;
        $totalCommissionAmount = 0;
        $contributingCompanies = [];
        $commissionRate = 0; // Declare commissionRate here

        if ($resellerNumber !== null) {
            // Fetch the reseller configuration
            $resellerConfig = ResellerLevelConfiguration::where('reseller_id', $this->id)->first();

            // Check if the reseller configuration is found
            if ($resellerConfig) {
                $proTarget = $resellerConfig->pro_target;
                $proRetainRate = $resellerConfig->pro_retain_rate;

                // Get the reseller's latest payout date and current date
                $oneYearAgo = Carbon::now()->subYear()->toDateString();
                $currentDate = Carbon::now()->toDateString();

                // Ensure $resellerNumber is set correctly
                $resellerNumber = $this->reseller_number;
                $reseller = User::where('id', $this->id)->first();
                if (! $reseller->reseller_level_change_at || $reseller->reseller_level_change_at <= $oneYearAgo) {
                    // Fetch the number of paid companies registered by the reseller in the last year
                    $totalCompaniesRegistered = Company::where('reseller_number', $resellerNumber)
                        ->where('companies.created_at', '>=', $oneYearAgo)
                        ->where('companies.created_at', '<=', $currentDate)
                        ->whereHas('subscriptions', function ($query): void {
                            $query->where('subscriptions.is_trial', BOOLEAN_FALSE);
                        })
                        ->count();

                    // Retain Customers
                    $retainedCompanies = Company::where('reseller_number', $resellerNumber)
                        ->where('companies.created_at', '>=', $oneYearAgo)
                        ->where('companies.created_at', '<=', $currentDate)
                        ->where('status', '!=', COMPANY_STATUS_BLOCKED)
                        ->whereHas('subscriptions', function ($query): void {
                            $query->where('subscriptions.is_trial', BOOLEAN_FALSE);
                        })
                        ->count();

                    // Calculate the retention rate
                    $retentionRate = $totalCompaniesRegistered > 0 ? ($retainedCompanies / $totalCompaniesRegistered) * 100 : 0;

                    // Check if the reseller meets the criteria
                    if ($totalCompaniesRegistered >= $proTarget && $retentionRate >= $proRetainRate) {
                        // Update the reseller's level
                        $reseller->reseller_level = 'Pro';
                    } else {
                        $reseller->reseller_level = 'Basic';
                    }
                    $reseller->reseller_level_change_at = $currentDate;
                    $reseller->save();
                }
            } else {
                // Handle the case where the reseller configuration is not found
                // For example, you can log an error or set a default level
                $reseller = User::where('id', $this->id)->first();
                $reseller->reseller_level = 'Basic';
                $reseller->save();
            }

            // Main query to get the count of paid customers
            $paidCustomersCount = Company::where('reseller_number', $resellerNumber)
                ->whereHas('subscriptions', function (Builder $query): void {
                    $query->where('subscriptions.created_at', function ($subQuery): void {
                        $subQuery->selectRaw('MAX(subscriptions.created_at)')
                            ->from('subscriptions')
                            ->whereColumn('subscriptions.company_id', 'companies.id');
                    })
                        ->where('subscriptions.is_trial', BOOLEAN_FALSE);
                })
                ->where('is_active', BOOLEAN_TRUE)
                ->count();

            // Main query to get the count of trial customers
            $trialCustomersCount = Company::where('reseller_number', $resellerNumber)
                ->whereHas('subscriptions', function (Builder $query): void {
                    $query->where('subscriptions.created_at', function ($subQuery): void {
                        $subQuery->selectRaw('MAX(subscriptions.created_at)')
                            ->from('subscriptions')
                            ->whereColumn('subscriptions.company_id', 'companies.id');
                    })
                        ->where('subscriptions.is_trial', BOOLEAN_TRUE);
                })
                ->count();

            // Determine the latest payout date if payout history is not empty
            $latestPayoutDate = $this->resellerPayoutHistory->isNotEmpty()
                ? Carbon::parse($this->resellerPayoutHistory->max('date'))
                : null;

            // Define the query to calculate the total amount
            $totalAmountQuery = Company::where('reseller_number', $resellerNumber)
                ->whereHas('subscriptions', function (Builder $query): void {
                    $query->where('subscriptions.created_at', function ($subQuery): void {
                        $subQuery->selectRaw('MAX(subscriptions.created_at)')
                            ->from('subscriptions')
                            ->whereColumn('subscriptions.company_id', 'companies.id');
                    })
                        ->where('subscriptions.is_trial', BOOLEAN_FALSE);
                })
                ->where('is_active', BOOLEAN_TRUE)
                ->join('company_user_balance_deductions', 'companies.id', '=', 'company_user_balance_deductions.company_id');

            // Conditionally apply filter based on latestPayoutDate
            if ($latestPayoutDate instanceof Carbon) {
                $totalAmountQuery->where('company_user_balance_deductions.created_at', '>', $latestPayoutDate);
            }

            // // Get the contributing companies and their amounts
            // $contributingCompanies = $totalAmountQuery->get([
            //     'companies.id as company_id',
            //     'companies.name as company_name',
            //     'company_user_balance_deductions.amount',
            // ]);
            $contributingCompanies = $totalAmountQuery->selectRaw('companies.id as company_id, companies.name as company_name, COALESCE(SUM(company_user_balance_deductions.amount)) as amount')
            ->groupBy('companies.id', 'companies.name')
            ->get();

            // Calculate the total amount
            $totalAmount = $contributingCompanies->sum('amount');

            // Determine the commission percentage based on the reseller level
            if ($this->reseller_level == 'Basic') {
                $commissionRate = $this->resellerLevelConfiguration->basic_commission ?? 0;
            } elseif ($this->reseller_level == 'Pro') {
                $commissionRate = $this->resellerLevelConfiguration->pro_commission ?? 0;
            }

            // Calculate the total commission amount
            $totalCommissionAmount = ($totalAmount * $commissionRate) / 100;
        }

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'user_type' => $this->user_type,
            'user_photo_id' => $userPhotoPath,
            // Set reseller_company_name and company_registration_document to null if user_type is individual
            'reseller_company_name' => $this->user_type === 'individual' ? null : $this->reseller_company_name,
            'company_registration_document' => $this->user_type === 'individual' ? null : $companyRegistrationDocPath,
            'reseller_number' => $this->reseller_number,
            'bank_details' => $this->resellerBankDetails ? [
                'account_title' => $this->resellerBankDetails->account_title,
                'bank_name' => $this->resellerBankDetails->bank_name,
                'account_number' => $this->resellerBankDetails->account_number,
                'branch_code' => $this->resellerBankDetails->branch_code,
                'city' => $this->resellerBankDetails->city,
                'country' => $this->resellerBankDetails->country,
            ] : null,
            'level_configuration' => $this->resellerLevelConfiguration ? [
                'basic_commission' => $this->resellerLevelConfiguration->basic_commission,
                'basic_retain_rate' => $this->resellerLevelConfiguration->basic_retain_rate,
                'basic_target' => $this->resellerLevelConfiguration->basic_target,
                'pro_commission' => $this->resellerLevelConfiguration->pro_commission,
                'pro_retain_rate' => $this->resellerLevelConfiguration->pro_retain_rate,
                'pro_target' => $this->resellerLevelConfiguration->pro_target,
            ] : null,
            'payout_history' => $this->resellerPayoutHistory->isNotEmpty() ? $this->resellerPayoutHistory->map(fn ($payout): array => [
                'id' => $payout->id,
                'reseller_id' => $payout->reseller_id,
                'reference_number' => $payout->reference_number,
                'account_number' => $payout->account_number,
                'amount' => (float) $payout->amount,
                'date' => $payout->date,
            ])->toArray() : null,
            'trial_customers_count' => $trialCustomersCount,
            'paid_customers_count' => $paidCustomersCount,
            'reseller_level' => $this->reseller_level,
            'reseller_balance' => $totalCommissionAmount,
            // With this part
            'contributing_companies' => empty($contributingCompanies) ? null : collect($contributingCompanies)->map(fn ($company): array => [
                'company_id' => $company->getAttribute('company_id'),
                'company_name' => $company->getAttribute('company_name'),
                'amount' => ($company->getAttribute('amount') * $commissionRate) / 100,
            ])->toArray(),
            'comments' => $this->comments->map(fn ($comment): array => [
                'id' => $comment->id,
                'description' => $comment->description,
                'created_by' => [
                    'id' => $comment->createdByUser->id,
                    'name' => $comment->createdByUser->name,
                ],
                'created_at' => $comment->created_at,
            ])->toArray(),

        ];
    }

    /**
     * Get the full URL for a file stored in public storage.
     */
    private function getPublicStorageUrl(string $path): string
    {
        // Remove the 'public/' prefix from the path if it exists
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        return asset('storage/'.$path);
    }
}
