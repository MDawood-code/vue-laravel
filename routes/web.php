<?php

use App\Models\User;
use App\Exports\CompanyNameImageExport;
use App\Http\Controllers\API\Admin\InvoicesController;
use App\Http\Controllers\API\TransactionsController;
use App\Notifications\FCMNotification;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', fn (): string => '');

// Transaction Slips
Route::get('invoice/{md5_string}', [TransactionsController::class, 'getInvoice']);
Route::get('companies/{company}/transactions/{transaction}', [TransactionsController::class, 'showSlip']);
Route::get('companies/{company}/transactions/{transaction}/generate-slip-pdf', [TransactionsController::class, 'generateSlipPDF']);

// Subscription Invoices
Route::get('companies/{company}/invoices/{invoice}', [InvoicesController::class, 'showTemplate']);

// Test Notification Routes
Route::get('test-reload/{user}', function (User $user): void {
    $user->notify(new FCMNotification('Notification Title', 'This is Notification Body', FCM_TYPE_RELOAD));
});

Route::get('test-notification/{user}', function (User $user): void {
    $user->notify(new FCMNotification('Notification Title', 'This is Notification Body'));
});

Route::get('company/name/image/excel/export', fn () => Excel::download(new CompanyNameImageExport, 'companies.xlsx'));

// Route::view('api-docs', 'scribe.index')->name('public_docs');
