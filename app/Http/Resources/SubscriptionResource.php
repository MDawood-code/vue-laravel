<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/** @mixin Subscription **/
class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        $plan_name = $this->name;
        $plan_name .= $this->is_trial && $this->validity_days == 30 ? ' (30 Days Trial)' : '';
        $end_date = null;
        $subscription_ending_in_days = null;
        if ($this->end_date) {
            $end_date = Carbon::createFromFormat('Y-m-d', $this->end_date);
            throw_if($end_date == false, new Exception("Invalid date format: {$this->end_date}"));
            $end_date = $end_date->endOfDay();

            $subscription_ending_in_days = Carbon::now()->startOfDay()->diffInDays($end_date);
        }

        return [
            'id' => $this->id,
            'name' => $plan_name,
            'type' => $this->type,
            'period' => $this->period,
            'amount' => $this->amount,
            'license_amount' => $this->license_amount,
            'license_discount' => $this->license_discount,
            'used_users_count' => $this->company->activeEmployees()->count(),
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'validity_days' => $this->validity_days,
            'subscription_ending_in_days' => $subscription_ending_in_days > 0 ? $subscription_ending_in_days : 0,
            'is_trial' => $this->is_trial,
            'status' => $this->status,
            'invoice' => $this->invoice ?? null,
            'created_at' => $this->created_at,
            'user_licenses' => $this->userLicenses,
            'user_licenses_count' => $this->user_licenses_count,
            'user_licenses_count_all' => $this->user_licenses_count_all,
            'company' => $this->company->name,
            'company_id' => $this->company_id,
            'balance' => $this->balance,
        ];
    }
}
