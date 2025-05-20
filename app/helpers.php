<?php

use App\Enums\AddonName;
use App\Models\Company;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;

if (! function_exists('user_is_admin')) {
    /**
     * Returns true if authenticated user is admin
     */
    function user_is_admin(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && $user->type === USER_TYPE_ADMIN;
    }
}

if (! function_exists('user_is_super_admin')) {
    /**
     * Returns true if authenticated user is super admin
     */
    function user_is_super_admin(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && $user->type === USER_TYPE_SUPER_ADMIN;
    }
}

if (! function_exists('user_is_admin_or_super_admin')) {
    /**
     * Returns true if authenticated user is admin or super admin
     */
    function user_is_admin_or_super_admin(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && in_array($user->type, [USER_TYPE_ADMIN,  USER_TYPE_SUPER_ADMIN]);
    }
}

if (! function_exists('user_is_staff')) {
    /**
     * Returns true if authenticated user is staff
     */
    function user_is_staff(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && $user->type === USER_TYPE_ADMIN_STAFF;
    }
}

if (! function_exists('user_is_admin_or_staff')) {
    /**
     * Returns true if authenticated user is admin_or_staff
     */
    function user_is_admin_or_staff(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && ($user->type === USER_TYPE_ADMIN || $user->type === USER_TYPE_ADMIN_STAFF);
    }
}

if (! function_exists('user_is_support_agent_staff')) {
    /**
     * Returns true if authenticated user is staff and support agent
     */
    function user_is_support_agent_staff(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && $user->type === USER_TYPE_ADMIN_STAFF && $user->is_support_agent == true;
    }
}

if (! function_exists('user_is_company_owner')) {
    /**
     * Returns true if authenticated user is company owner
     */
    function user_is_company_owner(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && $user->type === USER_TYPE_BUSINESS_OWNER;
    }
}

if (! function_exists('user_is_employee')) {
    /**
     * Returns true if authenticated user is company employee
     */
    function user_is_employee(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && $user->type === USER_TYPE_EMPLOYEE;
    }
}

if (! function_exists('user_is_customer')) {
    /**
     * Returns true if authenticated user is company owner or employee
     */
    function user_is_customer(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && in_array($user->type, [USER_TYPE_BUSINESS_OWNER,  USER_TYPE_EMPLOYEE]);
    }
}
if (! function_exists('user_is_referral')) {
    /**
     * Returns true if authenticated user is referral
     */
    function user_is_referral(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && $user->type === USER_TYPE_REFERRAL;
    }
}
if (! function_exists('user_is_reseller')) {
    /**
     * Returns true if authenticated user is reseller
     */
    function user_is_reseller(?User $user = null): bool
    {
        $user ??= auth()->guard('api')->user();

        return $user && $user->type === USER_TYPE_RESELLER;
    }
}
if (! function_exists('getTransactionTypeText')) {
    /**
     * Returns transaction text from transaction type
     */
    function getTransactionTypeText(int $transactionType): string
    {
        return match ($transactionType) {
            TRANSACTION_TYPE_CASH => 'Cash',
            TRANSACTION_TYPE_MADA => 'MADA',
            TRANSACTION_TYPE_STC => 'STC Pay',
            TRANSACTION_TYPE_CREDIT => 'Credit Card',
            TRANSACTION_TYPE_MULTIPAYMENT => 'Multi Payment',
            default => 'Cash',
        };
    }
}

if (! function_exists('canAccessFeatures')) {
    /**
     * Returns boolean whether company can access features
     */
    function canAccessFeatures(Company $company): bool
    {
        return in_array($company->status, [COMPANY_STATUS_ACTIVE, COMPANY_STATUS_REVIEW, COMPANY_STATUS_KYC]);
    }
}

if (! function_exists('getHumanReadableDateInDays')) {
    /**
     * Returns human readable date in days and ignore time
     */
    function getHumanReadableDateInDays(?Carbon $date): string
    {
        return $date instanceof Carbon ? ($date->isToday() ? 'Today' : $date->diffForHumans()) : 'Unknown';
    }
}

if (! function_exists('getSystemSubscriptionScheme')) {
    /**
     * Returns system subscription scheme value
     *
     * @return string
     */
    function getSystemSubscriptionScheme()
    {
        return SystemSetting::first()->subscription_scheme;
    }
}

if (! function_exists('hasActiveQrOrderingAddon')) {
    /**
     * Returns boolean.
     */
    function hasActiveQrOrderingAddon(User $user): bool
    {
        return $user->company->activeAddons()->whereHas('addon', function ($query): void {
            $query->where('name', AddonName::QrOrdering->value);
        })->exists();
    }
}

if (! function_exists('hasActiveTableManagementAddon')) {
    /**
     * Returns boolean.
     */
    function hasActiveTableManagementAddon(User $user): bool
    {
        return $user->company->activeAddons()->whereHas('addon', function ($query): void {
            $query->where('name', AddonName::TableManagement->value);
        })->exists();
    }
}

if (! function_exists('hasActiveOrderManagementAddon')) {
    /**
     * Returns boolean.
     */
    function hasActiveOrderManagementAddon(User $user): bool
    {
        return $user->company->activeAddons()->whereHas('addon', function ($query): void {
            $query->where('name', AddonName::OrderManagement->value);
        })->exists();
    }
}

if (! function_exists('hasActiveWaiterManagementAddon')) {
    /**
     * Returns boolean.
     */
    function hasActiveWaiterManagementAddon(User $user): bool
    {
        return $user->company->activeAddons()->whereHas('addon', function ($query): void {
            $query->where('name', AddonName::WaiterManagement->value);
        })->exists();
    }
}

if (! function_exists('hasActiveJobManagementAddon')) {
    /**
     * Returns boolean.
     */
    function hasActiveJobManagementAddon(User $user): bool
    {
        return $user->company->activeAddons()->whereHas('addon', function ($query): void {
            $query->where('name', AddonName::JobManagement->value);
        })->exists();
    }
}

if (! function_exists('getFrontendQrOrderingUrl')) {
    /**
     * Returns frontend url of qr ordering.
     */
    function getFrontendQrOrderingUrl(int $branchId, int|bool $isDriveThru, int $tableId): string
    {
        return config('frontend.qr_ordering_url') . '?src=' . encrypt($branchId) . '&is_drive_thru=' . $isDriveThru . '&table=' . encrypt($tableId);
    }
}

if (! function_exists('hasActiveStockAddon')) {
    /**
     * Returns boolean.
     *
     * @return bool
     */
    function hasActiveStockAddon(User $user)
    {
        return $user->company->activeAddons()->whereHas('addon', function ($query): void {
            $query->where('name', AddonName::Stock->value);
        })->exists();
    }
}

if (! function_exists('hasActiveTapToPayAddon')) {
    /**
     * Returns boolean.
     *
     * @return bool
     */
    function hasActiveTapToPayAddon(User $user)
    {
        return $user->company->activeAddons()->whereHas('addon', function ($query): void {
            $query->where('name', AddonName::TapToPay->value);
        })->exists();
    }
}

if (! function_exists('hasActiveCustomerManagementAddon')) {
    /**
     * Returns boolean.
     *
     * @return bool
     */
    function hasActiveCustomerManagementAddon(User $user)
    {
        return $user->company->activeAddons()->whereHas('addon', function ($query): void {
            $query->where('name', AddonName::CustomerManagement->value);
        })->exists();
    }
}

if (! function_exists('hasActiveA4SalesInvoiceAddon')) {
    /**
     * Returns boolean.
     *
     * @return bool
     */
    function hasActiveA4SalesInvoiceAddon(User $user)
    {
        return $user->company->activeAddons()->whereHas('addon', function ($query): void {
            $query->where('name', AddonName::A4SalesInvoice->value);
        })->exists();
    }
}

if (! function_exists('implodeWithAnd')) {
    /**
     * Implode an array of strings with commas and "and" before the last item.
     *
     * @param  array<string>  $items
     */
    function implodeWithAnd(array $items): string
    {
        // Filter out any empty items to ensure clean output
        $items = array_filter($items);

        if (count($items) > 1) {
            $last = array_pop($items); // Get the last item
            return implode(', ', $items) . ' and ' . $last;
        }

        return $items[0] ?? ''; // Return the single item or an empty string
    }
}
