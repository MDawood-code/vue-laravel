<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can generateLicenseInvoice.
     */
    public function generateLicenseInvoice(User $user): bool
    {
        return user_is_company_owner($user);
    }

    /**
     * Determine whether the user can generateDevicesPaymentInvoice.
     */
    public function generateDevicesPaymentInvoice(User $user): bool
    {
        return user_is_admin_or_super_admin($user) || user_is_staff($user);
    }

    /**
     * Determine whether the user can view deviceInvoices.
     */
    public function deviceInvoices(User $user, Device $device): bool
    {
        return user_is_admin_or_super_admin($user) || $user->company_id === $device->company_id;
    }

    /**
     * Determine whether the user can markInvoiceAsPaid.
     */
    public function markInvoiceAsPaid(User $user, Invoice $invoice): bool
    {
        return user_is_admin_or_super_admin($user) || (user_is_staff($user) && $invoice->company?->admin_staff_id === $user->id);
    }
}
