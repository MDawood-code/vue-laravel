<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\HelpdeskTicket;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Admin
 *
 * @subgroup Dashboard
 *
 * @subgroupDescription APIs for managing Dashboard
 */
class DashboardController extends Controller
{
    /**
     * Get dashboard data.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);

        $data = [];
        // if admin, show only admin related data
        if (user_is_admin_or_super_admin()) {
            $data['submitting_kyc_count'] = Company::where('status', COMPANY_STATUS_KYC)->count();
            $data['unpaid_invoices_count'] = Invoice::where('status', INVOICE_STATUS_UNPAID)->count();
            $data['inactive_customers_count'] = Company::where('status', COMPANY_STATUS_BLOCKED)->count();
            $data['active_customers_count'] = Company::where('status', COMPANY_STATUS_ACTIVE)->count();
            $data['under_review_customers_count'] = Company::where('status', COMPANY_STATUS_REVIEW)->count();
            $data['monthly_new_customers_count'] = Company::whereMonth('created_at', '=', Carbon::now()->month)->count();
            $data['monthly_inactive_customers_count'] = Company::whereMonth('updated_at', '=', Carbon::now()->month)->whereIn('status', [
                COMPANY_STATUS_BLOCKED,
                COMPANY_STATUS_SUBSCRIPTION_ENDED,
            ])->count();
            $data['idle_customers_count'] = Company::where('last_active_at', null)->orWhere('last_active_at', '<=', Carbon::now()->subDays(IDLE_CUSTOMER_DAYS))->count();
            $sub_near_expiry_count = 0;
            // Todo: Calculate count of companies whose active subscription is going to expire within 14 days
            $today = Carbon::now();
            $after_14 = Carbon::now()->addDays(14);
            $data['sub_near_expiry_count'] = DB::table('subscriptions')
                ->select(DB::raw('DISTINCT ON (company_id) *'))->whereBetween('end_date', [$today, $after_14])->orderBy('company_id')
                ->orderByDesc('created_at')
                ->count();
            $data['recharge_requests_count'] = Invoice::where('status', INVOICE_STATUS_UNPAID)->whereNotNull('stcpay_reference_id')->count();
            $data['new_tickets_count'] = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CREATED)->count();
            $data['in_progress_tickets_count'] = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_IN_PROGRESS)->count();
            $data['done_tickets_count'] = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_DONE)->count();
            $data['closed_tickets_count'] = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CLOSED)->count();
            $data['late_tickets_count'] = $this->helpdeskQuery()
                ->where('status', HELPDESK_TICKET_CREATED)
                ->where('created_at', '<=', Carbon::now()->subHours(24))
                ->count();
            $data['delayed_tickets_count'] = $this->helpdeskQuery()
                ->where('status', HELPDESK_TICKET_IN_PROGRESS)
                ->where('status_updated_at', '<=', Carbon::now()->subHours(48))
                ->count();

            // Accounts not created on Odoo
            // Invoices not stored on Odoo
            // Payments not stored on Odoo
            $data['failed_odoo_accounts'] = Company::absentOnOdoo()->count();
            $data['failed_odoo_invoices'] = Invoice::absentOnOdoo()->count();
            $data['failed_odoo_payments'] = Payment::done()->absentOnOdoo()->count();
        }

        // if admin staff, show only admin staff related data
        if (user_is_staff()) {
            $data['submitting_kyc_count'] = Company::where('status', COMPANY_STATUS_KYC)->adminStaffIsAuthUser()->count();
            $data['unpaid_invoices_count'] = Invoice::where('status', INVOICE_STATUS_UNPAID)
                ->whereHas('company', function (Builder $query): void {
                    /** @var Company $query */
                    $query->adminStaffIsAuthUser();
                })
                ->count();
            $data['inactive_customers_count'] = Company::where('status', COMPANY_STATUS_BLOCKED)->adminStaffIsAuthUser()->count();
            $data['active_customers_count'] = Company::where('status', COMPANY_STATUS_ACTIVE)->adminStaffIsAuthUser()->count();
            $data['under_review_customers_count'] = Company::where('status', COMPANY_STATUS_REVIEW)->adminStaffIsAuthUser()->count();
            $data['monthly_new_customers_count'] = Company::whereMonth('created_at', '=', Carbon::now()->month)->adminStaffIsAuthUser()->count();
            $data['monthly_inactive_customers_count'] = Company::whereMonth('updated_at', '=', Carbon::now()->month)->whereIn('status', [
                COMPANY_STATUS_BLOCKED,
                COMPANY_STATUS_SUBSCRIPTION_ENDED,
            ])->adminStaffIsAuthUser()->count();
            $data['idle_customers_count'] = Company::adminStaffIsAuthUser()->where('last_active_at', null)->orWhere('last_active_at', '<=', Carbon::now()->subDays(IDLE_CUSTOMER_DAYS))->count();
            $sub_near_expiry_count = 0;
            // Todo: Calculate count of companies whose active subscription is going to expire within 14 days
            $today = Carbon::now();
            $after_14 = Carbon::now()->addDays(14);
            $data['sub_near_expiry_count'] = DB::table('subscriptions')
                ->rightJoin('companies', 'subscriptions.company_id', '=', 'companies.id')
                // ->where('companies.admin_staff_id', auth()->id())
                ->latest('subscriptions.created_at')
                ->select('subscriptions.id', 'subscriptions.company_id', 'subscriptions.end_date')
                ->groupBy('subscriptions.company_id')
                ->whereBetween('subscriptions.end_date', [$today, $after_14])
                ->count();
            $data['recharge_requests_count'] = Invoice::where('status', INVOICE_STATUS_UNPAID)->whereNotNull('stcpay_reference_id')->whereHas('company', function ($query): void {
                $query->adminStaffIsAuthUser();
            })->count();
            if (user_is_support_agent_staff()) {
                $data['new_tickets_count'] = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CREATED)->count();
                $data['in_progress_tickets_count'] = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_IN_PROGRESS)->count();
                $data['done_tickets_count'] = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_DONE)->count();
                $data['closed_tickets_count'] = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CLOSED)->count();
                $data['late_tickets_count'] = $this->helpdeskQuery()
                    ->where('status', HELPDESK_TICKET_CREATED)
                    ->where('created_at', '<=', Carbon::now()->subHours(24))
                    ->count();
                $data['delayed_tickets_count'] = $this->helpdeskQuery()
                    ->where('status', HELPDESK_TICKET_IN_PROGRESS)
                    ->where('status_updated_at', '<=', Carbon::now()->subHours(48))
                    ->count();

                // Accounts not created on Odoo
                // Invoices not stored on Odoo
                // Payments not stored on Odoo
                $data['failed_odoo_accounts'] = Company::absentOnOdoo()->adminStaffIsAuthUser()->count();
                $data['failed_odoo_invoices'] = Invoice::absentOnOdoo()
                    ->whereHas('company', function (Builder $query): void {
                        /** @var Company $query */
                        $query->adminStaffIsAuthUser();
                    })
                    ->count();
                $data['failed_odoo_payments'] = Payment::absentOnOdoo()
                    ->whereHas('invoice.company', function (Builder $query): void {
                        /** @var Company $query */
                        $query->adminStaffIsAuthUser();
                    })
                    ->count();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Dashboard Data',
            'data' => $data,
        ], 201);
    }

    /** @return Builder<HelpdeskTicket> */
    private function helpdeskQuery(): Builder
    {
        $query = HelpdeskTicket::query();
        if (user_is_staff()) {
            $query = $query->where('assigned_to', auth()->id());
        }

        return $query;
    }
}
