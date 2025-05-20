<?php

use App\Http\Controllers\API\AddonController;
use App\Http\Controllers\API\Admin\AdminController;
use App\Http\Controllers\API\Admin\AdminStaffController;
use App\Http\Controllers\API\Admin\BranchesController as AdminBranchesController;
use App\Http\Controllers\API\Admin\BusinessTypeVerificationController;
use App\Http\Controllers\API\Admin\CitiesController;
use App\Http\Controllers\API\Admin\CustomerController;
use App\Http\Controllers\API\Admin\CompaniesController;
use App\Http\Controllers\API\Admin\CRM\ActivityController;
use App\Http\Controllers\API\Admin\CRM\ActivityTypeController;
use App\Http\Controllers\API\Admin\CRM\CommentController;
use App\Http\Controllers\API\Admin\CRM\CrmLogController;
use App\Http\Controllers\API\Admin\CRM\NoteController;
use App\Http\Controllers\API\Admin\CustomFeatureController;
use App\Http\Controllers\API\Admin\DashboardController;
use App\Http\Controllers\API\Admin\DeviceController;
use App\Http\Controllers\API\Admin\GetCrew;
use App\Http\Controllers\API\Admin\HelpdeskTicketController as AdminHelpdeskTicketController;
use App\Http\Controllers\API\Admin\InvoicesController;
use App\Http\Controllers\API\Admin\IssueTypeController;
use App\Http\Controllers\API\Admin\LearningSourceController;
use App\Http\Controllers\API\Admin\QuestionnaireController;
use App\Http\Controllers\API\Admin\ReferralCampaignController;
use App\Http\Controllers\API\Admin\ReferralController;
use App\Http\Controllers\API\Admin\RegionsController;
use App\Http\Controllers\API\Admin\ResellerCommentsController;
use App\Http\Controllers\API\Admin\ResellerController;
use App\Http\Controllers\API\Admin\SendFailedOdooResources;
use App\Http\Controllers\API\Admin\SubscriptionPlanController;
use App\Http\Controllers\API\Admin\SubscriptionsController as AdminSubscriptionsController;
use App\Http\Controllers\API\Admin\SystemSettingController;
use App\Http\Controllers\API\AppCrashLogsController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BranchesController;
use App\Http\Controllers\API\ChangeTransactionStatus;
use App\Http\Controllers\API\ChangeTransactionTable;
use App\Http\Controllers\API\ChangeTransactionWaiter;
use App\Http\Controllers\API\CitiesController as APICitiesController;
use App\Http\Controllers\API\CompanyAddonSubscriptionController;
use App\Http\Controllers\API\DevicesController;
use App\Http\Controllers\API\DiningTableController;
use App\Http\Controllers\API\DiscountController;
use App\Http\Controllers\API\ExternalIntegrationController;
use App\Http\Controllers\API\ForgotPasswordController;
use App\Http\Controllers\API\GetOrders;
use App\Http\Controllers\API\GetOrdersCounts;
use App\Http\Controllers\API\GetSaleInvoiceCounts;
use App\Http\Controllers\API\GetQrProducts;
use App\Http\Controllers\API\HelpdeskTicketController;
use App\Http\Controllers\API\NotificationController;
use App\Http\Controllers\API\PaymentController;
use App\Http\Controllers\API\PlaceQrOrder;
use App\Http\Controllers\API\ProductCategoriesController;
use App\Http\Controllers\API\ProductsController;
use App\Http\Controllers\API\ProductUnitsController;
use App\Http\Controllers\API\RegionsController as APIRegionsController;
use App\Http\Controllers\API\ReportsController;
use App\Http\Controllers\API\StockAdjustmentController;
use App\Http\Controllers\API\StockController;
use App\Http\Controllers\API\StockTransferController;
use App\Http\Controllers\API\SubscriptionsController;
use App\Http\Controllers\API\TransactionsController;
use App\Http\Controllers\API\UserController;
use App\Http\Resources\InvoiceCompactResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Authenticate Routes
Route::post('register', [AuthController::class, 'register'])
    ->name('register');
Route::post('register/validate', [AuthController::class, 'registerValidate'])
    ->name('register.validate');
Route::post('login', [AuthController::class, 'login'])
    ->name('login');

// Forgot Password Routes
Route::post('forgot-password', [ForgotPasswordController::class, 'forgotPassword']);
Route::post('verify/pin', [ForgotPasswordController::class, 'verifyPin']);
Route::post('reset-password', [ForgotPasswordController::class, 'resetPassword']);
Route::get('get-addons', [AddonController::class, 'getAddons'])->name('get-addons');
Route::middleware(['auth:api', 'track-company-active'])->group(function ($router): void {
    Route::get('is-login', [AuthController::class, 'isLogin'])
        ->name('is-login');
    Route::post('logout', [AuthController::class, 'logout'])
        ->name('logout');

    // User Routes
    Route::post('users/update', [UserController::class, 'update'])
        ->name('users.update');

    Route::post('users/app/config/update', [UserController::class, 'updateUserAppConfig'])
        ->name('users.app.config.update');

    // Product Routes
    Route::apiResource('products', ProductsController::class)->only([
        'index', 'store', 'destroy',
    ]);
    Route::post('products/{product}', [ProductsController::class, 'update'])->name('products.update');
    Route::post('products/{product}/odoo/send', [ProductsController::class, 'sendToOdoo']);

    // Product Categories Routes
    Route::apiResource('product-categories', ProductCategoriesController::class)->only([
        'index', 'store', 'update', 'destroy',
    ]);
    Route::get('categories/products', [ProductCategoriesController::class, 'categoriesWithProducts'])->name('categories.products');
    Route::post('product-categories/{category}/odoo/send', [ProductCategoriesController::class, 'sendToOdoo']);

    // Product Units Routes
    Route::apiResource('product-units', ProductUnitsController::class)->only([
        'index', 'store', 'update', 'destroy',
    ]);
    Route::post('product-units/{product_unit}/odoo/send', [ProductUnitsController::class, 'sendToOdoo']);

    // Transactions Routes
    Route::apiResource('transactions', TransactionsController::class)->only([
        'index', 'store', 'show',
    ]);
    // Sale Invoice Routes
    Route::post('create-sale-invoice', [TransactionsController::class, 'createSaleInvoice'])
    ->name('create-invoice');
    Route::get('sale-invoices', [TransactionsController::class, 'getInvoices']);
    Route::get('sale-invoice/{saleInvoice}', [TransactionsController::class, 'showSaleInvoice']);
    Route::post('sale-invoice-status-update/{saleInvoice}', [TransactionsController::class, 'saleInvoiceStatusUpdate']);
    Route::get('sale-invoices/counts', GetSaleInvoiceCounts::class)->name('sale-invoice.counts');
    // Register Payment Api for Sale Invoice
    Route::post('register-sale-invoice-payment', [TransactionsController::class, 'registerSaleInvoicePayment'])
    ->name('register-sale-invoice-payment');
    Route::get('get-sale-invoice-payments/{saleInvoice}', [TransactionsController::class, 'getSaleInvoicePayment'])
    ->name('get-sale-invoice-payments');
    Route::post('transactions/{transaction}/refund/{refund_type}', [TransactionsController::class, 'refund'])
        ->name('transactions.refund');
    Route::get('transactions/{transaction}/qrcode', [TransactionsController::class, 'getQRCode'])
        ->name('transactions.qrcode');
    Route::get('transactions/{transaction}/qrcode-base64', [TransactionsController::class, 'getQRCodeBase64'])
        ->name('transactions.qrcode_base64');
    Route::post('transactions/{transaction}/odoo/send', [TransactionsController::class, 'sendToOdoo']);
    // Get orders (transactions by status wise)
    Route::get('orders', GetOrders::class)->name('orders.index');
    Route::get('orders/counts', GetOrdersCounts::class)->name('orders.counts');
    Route::post('transactions/{transaction}/status/change', ChangeTransactionStatus::class)->name('transaction.status.change');
    Route::post('transactions/{transaction}/waiter/change', ChangeTransactionWaiter::class)->name('transaction.waiter.change');
    Route::post('transactions/{transaction}/table/change', ChangeTransactionTable::class)->name('transaction.table.change');
    Route::post('transactions/{transaction}/update', [TransactionsController::class, 'update'])->name('transactions.update');

    // Subscriptions Routes
    Route::apiResource('subscriptions', SubscriptionsController::class)->only([
        'index', 'store',
    ]);
    Route::get('subscriptions/renew', [SubscriptionsController::class, 'renew']);

    // Invoices Routes
    Route::get('invoices', [InvoicesController::class, 'index'])->name('invoices.index');
    Route::post('invoices/generate-license-invoice', [InvoicesController::class, 'generateLicenseInvoice'])
        ->name('invoices.generate_license_invoice');
    Route::get('invoices/{invoice}', [InvoicesController::class, 'show']);
    Route::get('companies/devices/{device}/invoices', [InvoicesController::class, 'deviceInvoices'])->name('companies.devices.invoices');

    // Employees Routes
    Route::get('employees', [UserController::class, 'indexEmployees'])
        ->name('employees.index');
    Route::post('employees', [UserController::class, 'storeEmployees'])
        ->name('employees.store');
    Route::post('employees/{employee}', [UserController::class, 'updateEmployees'])
        ->name('employees.update');
    Route::post('employees/{employee}/machine-user/toggle', [UserController::class, 'toggleEmployeeMachineUser'])
        ->name('employees.machine-user.toggle');
    Route::post('employees/{employee}/activate', [UserController::class, 'activateEmployees'])
        ->name('employees.activate');
    Route::post('employees/{employee}/deactivate', [UserController::class, 'deactivateEmployees'])
        ->name('employees.deactivate');
    Route::delete('employees/{employee}', [UserController::class, 'deleteEmployee'])
        ->name('employees.destroy');
    Route::post('employees/{employee}/odoo/send', [UserController::class, 'sendToOdoo']);

    // Branches Routes
    Route::apiResource('branches', BranchesController::class)->only([
        'index', 'store', 'update', 'destroy',
    ]);
    Route::post('branches/{branch}/odoo/send', [BranchesController::class, 'sendToOdoo']);

    // Payment Routes
    Route::apiResource('payments', PaymentController::class)->only([
        'index', 'store',
    ]);
    Route::post('payments/prepare-checkout', [PaymentController::class, 'checkoutRequest'])
        ->name('payments.prepare_checkout');
    Route::post('payments/verify', [PaymentController::class, 'verify'])
        ->name('payments.verify');
    Route::post('payments/balance/topup', [PaymentController::class, 'topUpBalance'])
        ->name('payments.topup_balance');

    // Devices Routes
    Route::get('devices', [DevicesController::class, 'index'])->name('devices.index');

    // Reports
    Route::get('reports/home-data-summary', [ReportsController::class, 'homeDataSummary'])
        ->name('reports.home_data_summary');
    Route::get('reports/sales-summary', [ReportsController::class, 'salesSummary'])
        ->name('reports.sales_summary');
    Route::get('reports/sales-by-items', [ReportsController::class, 'salesByItems'])
        ->name('reports.sales_by_items');
    Route::get('reports/sales-by-categories', [ReportsController::class, 'salesByCategories'])
        ->name('reports.sales_by_categories');
    Route::get('reports/refunds-by-items', [ReportsController::class, 'refundsByItems'])
        ->name('reports.refunds_by_items');
    Route::get('reports/refunds-by-categories', [ReportsController::class, 'refundsByCategories'])
        ->name('reports.refunds_by_categories');
    Route::get('reports/sales-by-branches', [ReportsController::class, 'salesByBranches'])
        ->name('reports.sales-by-branches');

    Route::apiResource('regions', APIRegionsController::class)->only(['index', 'show']);
    Route::apiResource('regions.cities', APICitiesController::class)->only(['index', 'show']);

    Route::post('helpdesk/tickets', [HelpdeskTicketController::class, 'store']);
    Route::get('helpdesk/tickets', [HelpdeskTicketController::class, 'index']);
    Route::get('helpdesk/tickets/new', [HelpdeskTicketController::class, 'newTickets']);
    Route::get('helpdesk/tickets/inprogress', [HelpdeskTicketController::class, 'inProgressTickets']);
    Route::get('helpdesk/tickets/done', [HelpdeskTicketController::class, 'doneTickets']);
    Route::get('helpdesk/tickets/closed', [HelpdeskTicketController::class, 'closedTickets']);

    Route::apiResource('discounts', DiscountController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('discounts/{discount}/update', [DiscountController::class, 'update']);
    Route::get('discounts/branches/index', [DiscountController::class, 'branchDiscounts']);

    // External Integrations with systems like Odoo, Zoho etc.
    Route::apiResource('external/integrations', ExternalIntegrationController::class)->only(['index', 'store']);
    Route::post('external/integrations/connection', [ExternalIntegrationController::class, 'testConnection']);
    Route::get('external/integrations/types', [ExternalIntegrationController::class, 'types']);
    Route::delete('external/integrations/{external_integration}', [ExternalIntegrationController::class, 'destroy']);

    // Export to Excel
    Route::get('transactions/excel/export', [TransactionsController::class, 'export']);

    // Download templates
    Route::get('product-units/template/excel/export', [ProductUnitsController::class, 'exportUnitsTemplate']);
    Route::get('product-categories/template/excel/export', [ProductCategoriesController::class, 'exportCategoriesTemplate']);
    Route::get('products/template/excel/export', [ProductsController::class, 'exportProductsTemplate']);

    // Import from Excel
    Route::post('product-units/excel/import', [ProductUnitsController::class, 'import']);
    Route::post('product-categories/excel/import', [ProductCategoriesController::class, 'import']);
    Route::post('products/excel/import', [ProductsController::class, 'import']);

    // Notifications
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{notification}/markAsRead', [NotificationController::class, 'markAsRead']);
    Route::post('notifications/markAllAsRead', [NotificationController::class, 'markAllAsRead']);
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

    // Get available addons
    Route::get('addons', [AddonController::class, 'index'])->name('addons');
    // Subscribe an Addon
    Route::post('companies/addons/{addon}', [CompanyAddonSubscriptionController::class, 'subscribe'])->name('company.addon.subscription');
    Route::post('companies/addons/{addon}/unsubscribe', [CompanyAddonSubscriptionController::class, 'unsubscribe'])->name('company.addon.unsubscribe');

    // Dining Tables
    Route::apiResource('diningTables', DiningTableController::class)->only('index', 'show', 'store');
    Route::post('diningTables/{diningTable}/update', [DiningTableController::class, 'update'])->name('dining.tables.update');
    Route::post('diningTables/{diningTable}/delete', [DiningTableController::class, 'destroy'])->name('dining.tables.delete');

    //Stock API
    Route::get('stock', [StockController::class, 'index']);
    Route::get('products/{productId}/stock', [StockController::class, 'show']);
    Route::get('branches/{branchId}/products/stock', [StockController::class, 'getBranchProducts']);

    //Stock Adjustment
    Route::post('stock-adjustments', [StockAdjustmentController::class, 'store']);
    Route::get('stock-adjustments', [StockAdjustmentController::class, 'index']);

    //Stock Transfer Product
    Route::post('stock-transfers/store', [StockTransferController::class, 'store']);
    Route::post('stock-transfers/request', [StockTransferController::class, 'request']);
    Route::post('stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve']);
    Route::post('stock-transfers/{stockTransfer}/cancel-reject', [StockTransferController::class, 'cancelOrReject']);
    Route::post('stock-transfers/{stockTransfer}/update', [StockTransferController::class, 'update']);
    Route::get('stock-transfers', [StockTransferController::class, 'index']);
    //Customer 
    Route::get('customers-concise', [CustomerController::class, 'conciseCustomers']);
     Route::apiResource('customer', CustomerController::class)->only([
        'index', 'store', 'show', 'destroy',
    ]);
    Route::get('customer-transactions/{customer}', [CustomerController::class, 'customerTransactions'])->name('customer.transactions');
    Route::post('customer/{customer}/update', [CustomerController::class, 'update'])->name('customer.update');
});
// Guest can access the transaction
// Used in Odoo
Route::get('transactions/{id}/invoice', [TransactionsController::class, 'showByEncryptedId']);

Route::post('send-otp-sms', [AuthController::class, 'sendOTPSMS']);

// Subscription Plans
Route::get('subscription-plans', [SubscriptionPlanController::class, 'getSingleInstance'])
    ->name('subscription_plans.get_single_instance');

// qr products and order for a branch
Route::get('qr/products', GetQrProducts::class)->name('qr.products.index');
Route::post('qr/orders', PlaceQrOrder::class)->name('qr.order.store');

// SuperAdmin Routes
Route::middleware('auth:api')->prefix('super/admin')->name('super.admin.')->group(function ($router): void {
    Route::apiResource('admins', AdminController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('admins/{user}/update', [AdminController::class, 'update'])->name('admins.update');
    Route::post('custom/features/{customFeature}', [CustomFeatureController::class, 'update'])->name('custom.features.update');
    Route::get('system/settings', [SystemSettingController::class, 'show'])->name('system.settings.show');
    Route::post('system/settings', [SystemSettingController::class, 'update'])->name('system.settings.update');
    Route::post('subscription/plans/{plan}', [SubscriptionPlanController::class, 'update'])->name('subscription.plans.update');
    // APIs for creating and updating addons
    Route::post('addons', [AddonController::class, 'store'])->name('addons.store');
    Route::post('addons/{addon}', [AddonController::class, 'update'])->name('addons.update');
});

// Admin Routes
Route::middleware('auth:api')->prefix('admin')->name('admin.')->group(function ($router): void {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // Companies Routes
    Route::get('companies', [CompaniesController::class, 'index'])
        ->name('companies.index');
    Route::get('companies/{company}', [CompaniesController::class, 'show'])
        ->name('companies.show');
    // Route::post('companies/{company}', [CompaniesController::class, 'update'])
    //     ->name('companies.update');
    Route::post('companies/{company}/activate', [CompaniesController::class, 'activate'])
        ->name('companies.activate');
    Route::post('companies/{company}/deactivate', [CompaniesController::class, 'deactivate'])
        ->name('companies.deactivate');
    Route::post('companies/{company}/update-details', [CompaniesController::class, 'updateDetails'])
        ->name('companies.details.update');
    Route::delete('companies/{company}/destroy', [CompaniesController::class, 'destroy']);
    Route::post('companies/{company}/csr/{user}/change', [CompaniesController::class, 'changeAdminStaff']);
    Route::post('companies/{company}/change-reseller', [CompaniesController::class, 'changeReseller']);
    Route::post('companies/{company}/uploaded/file/delete', [CompaniesController::class, 'deleteUploadedFile']);

    // Branches Routes
    Route::post('companies/{company}/branches/{branch}', (new AdminBranchesController)->update(...))->name('companies.branches.update');
    Route::apiResource('companies/{company}/branches', AdminBranchesController::class)->only(['store', 'destroy']);

    // Subscriptions
    Route::delete('subscriptions/{subscription}', [SubscriptionsController::class, 'destroy'])
        ->name('subscriptions.destroy');

    // Invoices
    Route::get('companies/{company_id}/invoices', [InvoicesController::class, 'index'])
        ->name('invoices.index');
    Route::post('invoices', [InvoicesController::class, 'store'])
        ->name('invoices.store');
    Route::post('invoices/companies/{company}/generate-devices-payment-invoice', [InvoicesController::class, 'generateDevicesPaymentInvoice'])
        ->name('invoices.generateDevicesPaymentInvoice');

    // Devices
    Route::get('companies/{company}/devices', [DeviceController::class, 'index'])->name('companies.devices.index');
    Route::post('companies/{company}/devices', [DeviceController::class, 'store'])->name('companies.devices.store');
    Route::get('companies/devices/{device}', [DeviceController::class, 'show'])->name('companies.devices.show');
    Route::post('companies/devices/{device}', [DeviceController::class, 'update'])->name('companies.devices.update');
    Route::delete('companies/devices/{device}', [DeviceController::class, 'destroy'])->name('companies.devices.destroy');

    // Assign annual subscription to company if they have bought a device
    Route::post('companies/{company}/subscriptions/activate-annual', (new AdminSubscriptionsController)->activateAnnualTrialSubscription(...))->name('companies.subscriptions.activateAnnual');
    // Extend trial subscription to 1 year
    Route::post('companies/{company}/subscriptions/trial/extend', (new AdminSubscriptionsController)->extendTrialSubscription(...))->name('companies.subscriptions.trial.extend');
    Route::get('companies/subscriptions/list', (new AdminSubscriptionsController)->index(...))->name('companies.subscriptions.index');

    Route::post('invoices/{invoice}/mark-as-paid', [InvoicesController::class, 'markInvoiceAsPaid'])->name('invoices.markAsPaid');

    Route::apiResource('regions', RegionsController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('regions/{region}/update', [RegionsController::class, 'update'])->name('regions.update');

    Route::apiResource('regions.cities', CitiesController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('regions/{region}/cities/{city}/update', [CitiesController::class, 'update'])->name('regions.cities.update');

    Route::apiResource('staff', AdminStaffController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('staff/{user}/update', [AdminStaffController::class, 'update'])->name('staff.update');

    // Issue Types
    Route::post('issue/types', [IssueTypeController::class, 'store']);
    Route::post('issue/types/{issue_type}', [IssueTypeController::class, 'update']);

    // Issue Types
    // Route::get('business/type/verifications', [BusinessTypeVerificationController::class, 'index']);
    Route::post('business/type/verifications/{business_type_verification}', [BusinessTypeVerificationController::class, 'update']);
    Route::delete('business/type/verifications/{business_type_verification}', [BusinessTypeVerificationController::class, 'destroy']);
    Route::resource('business/type/verifications', BusinessTypeVerificationController::class)->only(['index', 'store']);

    // Send failed companies, invoices and payments to Odoo
    Route::post('odoo/resources/failed/send', SendFailedOdooResources::class);
    // Update company on Odoo
    Route::post('odoo/companies/{company}/update', [CompaniesController::class, 'updateCompanyOnOdoo']);

    // CRM
    // Activity types
    Route::apiResource('crm/activity/types', ActivityTypeController::class)->only(['index', 'store']);
    Route::get('crm/activity/types/{activity_type}', [ActivityTypeController::class, 'show']);
    Route::post('crm/activity/types/{activity_type}/update', [ActivityTypeController::class, 'update']);
    // Activity
    Route::apiResource('crm/companies/{company}/activities', ActivityController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::get('crm/activities/all', [ActivityController::class, 'all']);
    Route::post('crm/companies/{company}/activities/{activity}/update', [ActivityController::class, 'update']);
    Route::post('crm/companies/{company}/activities/{activity}/status/update', [ActivityController::class, 'updateStatus']);
    Route::get('crm/activities/{activity}/comments', [ActivityController::class, 'comments']);
    // Comment
    Route::apiResource('crm/comments', CommentController::class)->only(['store', 'show', 'destroy']);
    Route::post('crm/comments/{comment}/update', [CommentController::class, 'update']);
    // Comment
    Route::apiResource('crm/companies/{company}/notes', NoteController::class)->only(['index', 'store', 'show', 'destroy']);
    Route::post('crm/companies/{company}/notes/{note}/update', [NoteController::class, 'update']);
    Route::get('crm/notes/{note}/comments', [NoteController::class, 'comments']);
    // Logs
    Route::get('crm/companies/{company}/logs', [CrmLogController::class, 'index']);
    // AnyPOS Crew i.e. admins and admin staff
    Route::get('crew', GetCrew::class);

    // Questionnaire
    Route::get('learning/sources', LearningSourceController::class);
    Route::post('questionnaires/companies/{company}', [QuestionnaireController::class, 'store']);
    Route::get('questionnaires/companies/{company}', [QuestionnaireController::class, 'companyQuestionnaire']);

    //Referrales
    Route::apiResource('referrals', ReferralController::class)->only([
        'index', 'store', 'show', 'destroy',
    ]);
    Route::post('referrals/{referral}/update', [ReferralController::class, 'update'])->name('referrals.update');
    // Referral Campaigns
    Route::post('referrals/campaign/{referral}/store', [ReferralCampaignController::class, 'store']);
    Route::post('referrals/campaign/{referral}/{referralCampaign}/activate', [ReferralCampaignController::class, 'activate']);
    Route::post('referrals/campaign/{referralCampaign}/deactivate', [ReferralCampaignController::class, 'deactivate']);
    // Reseller
    Route::apiResource('reseller', ResellerController::class)->only([
        'index', 'store', 'show', 'destroy',
    ]);
    Route::post('reseller/{reseller}/update', [ResellerController::class, 'update'])->name('reseller.update');
    Route::post('reseller/bank-details/{reseller}/store', [ResellerController::class, 'addBankDetails']);
    Route::post('reseller/level-configuration/{reseller}/store', [ResellerController::class, 'addLevelConfiguration']);
    Route::post('reseller/payout-history/{reseller}/store', [ResellerController::class, 'addPayouthistory']);

    Route::post('reseller/{reseller}/upgrade', [ResellerController::class, 'upgrade'])
        ->name('reseller.upgrade');
    Route::post('reseller/{reseller}/degrade', [ResellerController::class, 'degrade'])
        ->name('reseller.degrade');
    Route::get('payout-details/{reseller_id}/{payout_id}', [ResellerController::class, 'getPayoutDetails']);
    Route::post('reseller/{reseller}/changeStatus', [ResellerController::class, 'changeStatus'])
        ->name('reseller.changeStatus');
    //Reseller Comments
    Route::apiResource('reseller/comments', ResellerCommentsController::class)->only(['store', 'show', 'destroy']);
    Route::post('reseller/comments/{comment}/update', [ResellerCommentsController::class, 'update']);
   

});

// Support Agent Routes. Admin can also access
Route::middleware('auth:api')->prefix('support/agent')->name('support.agent.')->group(function ($router): void {
    Route::get('helpdesk/tickets', (new AdminHelpdeskTicketController)->index(...));
    Route::get('helpdesk/tickets/new', (new AdminHelpdeskTicketController)->newTickets(...));
    Route::get('helpdesk/tickets/inprogress', (new AdminHelpdeskTicketController)->inProgressTickets(...));
    Route::get('helpdesk/tickets/done', (new AdminHelpdeskTicketController)->doneTickets(...));
    Route::get('helpdesk/tickets/closed', (new AdminHelpdeskTicketController)->closedTickets(...));
    Route::get('helpdesk/tickets/late', (new AdminHelpdeskTicketController)->lateTickets(...));
    Route::get('helpdesk/tickets/delayed', (new AdminHelpdeskTicketController)->delayedTickets(...));
    Route::post('helpdesk/tickets/{helpdesk_ticket}/update', (new AdminHelpdeskTicketController)->update(...));

    Route::get('issue/types', [IssueTypeController::class, 'index']);
    Route::get('issue/types/{issue_type}', [IssueTypeController::class, 'show']);
});
// Referral
Route::middleware('auth:api')->prefix('referral')->group(function ($router): void {
    Route::get('referral-companies', [ReferralController::class, 'referralCompanies']);
    Route::get('companies/{company}', [ReferralController::class, 'showCompany'])
        ->name('companies.show');

});
// Reseller
Route::middleware('auth:api')->prefix('reseller')->group(function ($router): void {
    Route::get('reseller-dashboard', [ResellerController::class, 'dashboard']);
    Route::get('reseller-companies', [ResellerController::class, 'resellerCompanies']);
    Route::get('companies/{company}', [ResellerController::class, 'showCompany'])
        ->name('companies.show');
    //update reseller
    Route::post('update', [ResellerController::class, 'updateReseller']);
    // Tickets
    Route::get('helpdesk/tickets', [ResellerController::class, 'helpdeskShow']);
    Route::get('helpdesk/tickets/new', [ResellerController::class, 'newTickets']);
    Route::get('helpdesk/tickets/inprogress', [ResellerController::class, 'inProgressTickets']);
    Route::get('helpdesk/tickets/done', [ResellerController::class, 'doneTickets']);
    Route::get('helpdesk/tickets/closed', [ResellerController::class, 'closedTickets']);
    Route::get('helpdesk/tickets/late', [ResellerController::class, 'lateTickets']);
    Route::get('helpdesk/tickets/delayed', [ResellerController::class, 'delayedTickets']);
    Route::post('helpdesk/tickets/{helpdesk_ticket}/update', [ResellerController::class, 'updateTicket']);
    Route::post('helpdesk/tickets/{helpdesk_ticket}/forward', [ResellerController::class, 'forwardTicket']);

});
Route::post('get-body', fn (Request $request) => response()->json(['success' => true, 'message' => '', 'data' => $request->all()]));

Route::post('test/receive', fn (Request $request) => $request->all());
Route::get('test/callhttp', function () {
    // Call api for odoo that invoice has been generated
    $response = Http::post(url('/').'/api/test/receive', ['invoice' => new InvoiceCompactResource(Invoice::find(33))]);

    return $response->body();
});

// App Crash Logs Routes
Route::get('app-crash-logs/', [AppCrashLogsController::class, 'index']);
Route::get('app-crash-logs/store', [AppCrashLogsController::class, 'store']);
Route::get('app-crash-logs/{appCrashLog}', [AppCrashLogsController::class, 'show']);
