<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referral\StoreReferralRequest;
use App\Http\Requests\Referral\UpdateReferralRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\ReferralResource;
use App\Http\Resources\SingleReferralResource;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Stringable;

class ReferralController extends Controller
{
    /**
     * Display a listing of Referrals.
     */
    public function index(): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);

        $referrals = User::where('type', USER_TYPE_REFERRAL)->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Referral List Response',
            'data' => [
                'referrals' => ReferralResource::collection($referrals),
                'pagination' => [
                    'total' => $referrals->total(),
                    'current_page' => $referrals->currentPage(),
                    'per_page' => $referrals->perPage(),
                    'total_pages' => ceil($referrals->total() / $referrals->perPage()),
                    'has_more_pages' => $referrals->hasMorePages(),
                    'next_page_url' => $referrals->nextPageUrl(),
                    'previous_page_url' => $referrals->previousPageUrl(),
                ],
            ],
        ]);
    }

    /**
     * Show the specified referral.
     */
    public function show(User $referral): JsonResponse
    {
        $this->authorize('adminDashboard', $referral);
        $referral->load('referralCampaigns');

        return response()->json([
            'success' => true,
            'message' => 'Referral Resource',
            'data' => [
                'referral' => new SingleReferralResource($referral),
            ],
        ], 200);
    }

    /**
     * store newly referrals.
     */
    public function store(StoreReferralRequest $request): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);
        $data = $request->safe()->except(['password', 'is_active']);
        $data += [
            'password' => bcrypt($request->password),
            'is_active' => boolval($request->is_active),
            'app_config' => '{"direction":"ltr", "allowEditablePrice": true}',
            'type' => USER_TYPE_REFERRAL,
        ];
        $referral = User::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Referral Added Successfully!',
            'data' => [
                'referral' => new ReferralResource($referral),
            ],
        ], 201);
    }

    /**
     * Delete the specified referral .
     */
    public function destroy(User $referral): JsonResponse
    {
        $this->authorize('adminDashboard', $referral);

        $referral->delete();

        return response()->json([
            'success' => true,
            'message' => 'Referral Deleted Successfully!',
            'data' => [],
        ], 201);
    }

    /**
     * Update the specified referral.
     */
    public function update(UpdateReferralRequest $request, User $referral): JsonResponse
    {
        $this->authorize('adminDashboard', $referral);

        $referral->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Referral Updated Successfully!',
            'data' => [
                'referral' => new ReferralResource($referral),
            ],
        ], 201);
    }

    /**
     * get referral Companies.
     */
    public function referralCompanies(): JsonResponse
    {
        try {
            $this->authorize('referralDashboard', User::class);

            $user = Auth::user();
            $referralCodes = $user->referralCampaigns()->pluck('referral_code')->toArray();

            // Retrieve users with referral codes matching the user's referral campaigns
            $companies = Company::whereIn('referral_code', $referralCodes)->latest()
                ->paginate(15);

            return response()->json([
                'success' => true,
                'message' => 'Companies List Response',
                'data' => [
                    'companies' => CompanyResource::collection($companies),
                    'pagination' => [
                        'total' => $companies->total(),
                        'current_page' => $companies->currentPage(),
                        'per_page' => $companies->perPage(),
                        'total_pages' => ceil($companies->total() / $companies->perPage()),
                        'has_more_pages' => $companies->hasMorePages(),
                        'next_page_url' => $companies->nextPageUrl(),
                        'previous_page_url' => $companies->previousPageUrl(),
                    ],
                ],
            ], 200);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        } catch (Exception) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
            ], 500);
        }

    }

    /**
     * Show Company Details.
     */
    public function showCompany(Request $request, Company $company): JsonResponse
    {
        $this->authorize('referralDashboard', User::class);

        $company->load('activeAddons.addon');
        $company_name = is_null($request->company_name) ? null : $request->string('company_name');
        $status = is_null($request->status) ? null : $request->integer('status');
        $cr_number = is_null($request->CR_number) ? null : $request->string('CR_number');
        $phone = is_null($request->phone) ? null : $request->string('phone');
        $monthly_new = is_null($request->monthly_new) ? null : $request->boolean('monthly_new');
        $monthly_inactive = is_null($request->monthly_inactive) ? null : $request->boolean('monthly_inactive');
        $failed_odoo_accounts = is_null($request->failed_odoo_accounts) ? null : $request->boolean('failed_odoo_accounts');
        $idle = is_null($request->idle) ? null : $request->boolean('idle');

        $prev_company_id = $this->companiesQuery()
            ->where('id', '<', $company->id)
            ->when($company_name, function (Builder $query, ?Stringable $company_name): void {
                $query->where('name', 'like', '%'.$company_name.'%');
            })
            ->when($status, function (Builder $query, ?int $status): void {
                $query->where('status', $status);
            })
            ->when($cr_number, function (Builder $query, ?Stringable $cr_number): void {
                $query->where('cr', $cr_number);
            })
            ->when($phone, function (Builder $query, ?Stringable $phone): void {
                $query->whereHas('users', function (Builder $q) use ($phone): void {
                    $q->where('phone', 'like', '%'.$phone.'%');
                });
            })
            ->when($monthly_new, function (Builder $query, ?bool $monthly_new): void {
                $query->whereMonth('created_at', '=', Carbon::now()->month);
            })
            ->when($monthly_inactive, function (Builder $query, ?bool $monthly_inactive): void {
                $query->whereMonth('updated_at', '=', Carbon::now()->month)->whereIn('status', [
                    COMPANY_STATUS_BLOCKED,
                    COMPANY_STATUS_SUBSCRIPTION_ENDED,
                ]);
            })
            ->when($failed_odoo_accounts, function (Builder $query, ?bool $failed_odoo_accounts): void {
                $query->absentOnOdoo();
            })
            ->when($idle, function (Builder $query, ?bool $idle): void {
                $query->where(function ($query): void {
                    $query->where('last_active_at', null)
                        ->orWhere('last_active_at', '<=', Carbon::now()->subDays(IDLE_CUSTOMER_DAYS));
                });
            })
            ->max('id');

        $next_company_id = $this->companiesQuery()
            ->where('id', '>', $company->id)
            ->when($company_name, function (Builder $query, ?Stringable $company_name): void {
                $query->where('name', 'like', '%'.$company_name.'%');
            })
            ->when($status, function (Builder $query, ?int $status): void {
                $query->where('status', $status);
            })
            ->when($cr_number, function (Builder $query, ?Stringable $cr_number): void {
                $query->where('cr', $cr_number);
            })
            ->when($phone, function (Builder $query, ?Stringable $phone): void {
                $query->whereHas('users', function (Builder $q) use ($phone): void {
                    $q->where('phone', 'like', '%'.$phone.'%');
                });
            })
            ->when($monthly_new, function (Builder $query, ?bool $monthly_new): void {
                $query->whereMonth('created_at', '=', Carbon::now()->month);
            })
            ->when($monthly_inactive, function (Builder $query, ?bool $monthly_inactive): void {
                $query->whereMonth('updated_at', '=', Carbon::now()->month)->whereIn('status', [
                    COMPANY_STATUS_BLOCKED,
                    COMPANY_STATUS_SUBSCRIPTION_ENDED,
                ]);
            })
            ->when($failed_odoo_accounts, function (Builder $query, ?bool $failed_odoo_accounts): void {
                $query->absentOnOdoo();
            })
            ->when($idle, function (Builder $query, ?bool $idle): void {
                $query->where(function ($query): void {
                    $query->where('last_active_at', null)
                        ->orWhere('last_active_at', '<=', Carbon::now()->subDays(IDLE_CUSTOMER_DAYS));
                });
            })
            ->min('id');

        return response()->json([
            'success' => true,
            'message' => 'Company Response.',
            'data' => [
                'prev_company_id' => $prev_company_id,
                'next_company_id' => $next_company_id,
                'company' => new CompanyResource($company, true, true, true),
            ],
        ], 201);
    }

    /** @return Builder<Company> */
    private function companiesQuery(): Builder
    {
        $query = Company::query();
        if (user_is_staff()) {
            if (auth()->guard('api')->user()->can_manage_all_regions == true) {
                $query = $query->where('admin_staff_id', auth()->id());
            } else {
                $cities_ids = auth()->user()->cities->pluck('cities.id');
                $query = $query->whereIn('billing_city', $cities_ids);
            }
        }

        return $query;
    }
}
