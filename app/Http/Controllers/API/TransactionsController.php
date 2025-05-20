<?php

namespace App\Http\Controllers\API;

use App\Enums\AddonName;
use App\Enums\BalanceDeductionType;
use App\Enums\TransactionOrderSource;
use App\Enums\TransactionStatus;
use App\Enums\SaleInvoiceStatus;
use App\Events\OdooTransactionCreated;
use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\RefundTransactionRequest;
use App\Http\Requests\SaleInvoice\StoreSaleInvoiceRequest;
use App\Http\Requests\SaleInvoice\RegisterSaleInvoicePaymentRequest;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\SaleInvoiceResource;
use App\Http\Resources\SaleInvoicePaymentResource;
use App\Models\Company;
use App\Models\CustomFeature;
use App\Models\SaleInvoicePayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Salla\ZATCA\GenerateQrCode;
use Salla\ZATCA\Tags\InvoiceDate;
use Salla\ZATCA\Tags\InvoiceTaxAmount;
use Salla\ZATCA\Tags\InvoiceTotalAmount;
use Salla\ZATCA\Tags\Seller;
use Salla\ZATCA\Tags\TaxNumber;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * @group Customer
 *
 * @subgroup Transaction
 *
 * @subgroupDescription APIs for managing Transaction
 */
class TransactionsController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of the resource.
     *
     * @queryParam search_start_date string The start date for the transaction search. Example: 2022-01-01
     * @queryParam search_end_date string The end date for the transaction search. Example: 2022-01-31
     * @queryParam is_refunded string The refund status of the transactions. Example: 0. Allowed values 0,1
     * @queryParam type string The type of the transactions. Example: 1. Allowed values 1,2,3,4,5
     * @queryParam filter_by_branch string The name of the branch to filter the transactions. Example: Branch A
     * @queryParam pageSize int The number of transactions to include per page. Example: 10
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Transaction::class);

        if ($this->loggedInUser->type === USER_TYPE_BUSINESS_OWNER) {
            $transactions = Transaction::where('company_id', $this->loggedInUser->company_id)->with('user');
        } else {
            $transactions = Transaction::where('branch_id', $this->loggedInUser->branch_id)
                ->when(auth()->user()->is_waiter, function ($query): void {
                    $query->where(function ($q): void {
                        $q->where('user_id', auth()->id())
                            ->orWhere('waiter_id', auth()->id());
                    });
                })
                ->with('user');
        }

        if ($request->search_start_date && $request->search_end_date) {
            $start_date = Carbon::parse($request->search_start_date)->toDateTimeString();
            $end_date = Carbon::parse($request->search_end_date)->toDateTimeString();
            $transactions = $transactions->where('created_at', '>=', $start_date)
                ->where('created_at', '<=', $end_date);
        }
        if (in_array($request->is_refunded, ['0', '1'])) {
            $transactions = $transactions->where('is_refunded', boolval($request->is_refunded));
        }
        if (in_array($request->type, ['1', '2', '3', '4', '5'])) {
            $transactions = $transactions->where('type', intval($request->type));
        }

        $transactions = $transactions->with(['items', 'multipayments', 'waiter', 'discount', 'diningTable', 'branch', 'refundTransactions', 'referenceTransaction', 'user','customer'])
            ->when(request('filter_by_branch'), function ($query): void {
                $query->whereHas('branch', function ($q): void {
                    $q->where('name', request('filter_by_branch'));
                });
            })
            ->where('status', TransactionStatus::Completed)
            ->latest()
            ->paginate($request->pageSize ?? 15);

        return response()->json([
            'success' => true,
            'message' => 'Transactions List Response',
            'data' => [
                'transactions' => TransactionResource::collection($transactions),
                'pagination' => [
                    'total' => $transactions->total(),
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total_pages' => ceil($transactions->total() / $transactions->perPage()),
                    'has_more_pages' => $transactions->hasMorePages(),
                    'next_page_url' => $transactions->nextPageUrl(),
                    'previous_page_url' => $transactions->previousPageUrl(),
                ],
            ],

        ], 200);
    }

    /**
     * Display a resource.
     */
    public function show(Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        $transaction->load(['items', 'multipayments', 'waiter', 'discount', 'diningTable', 'branch', 'refundTransactions', 'referenceTransaction', 'user','customer']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction Response',
            'data' => [
                'transaction' => new TransactionResource($transaction),
            ],

        ], 200);
    }

    /**
     * Display a resource by encrypted id.
     *
     * @unauthenticated
     */
    public function showByEncryptedId(string $id): JsonResponse
    {
        try {
            /** @var ?Transaction $transaction */
            $transaction = Transaction::find(decrypt($id));
            if ($transaction) {
                $transaction->load(['items', 'multipayments']);

                $qr_code = GenerateQrCode::fromArray([
                    // Generate QR Code as per Transaction Details
                    new Seller($transaction->company->name), // seller name
                    new TaxNumber($transaction->company->vat), // seller tax number
                    new InvoiceDate($transaction->created_at), // invoice date
                    new InvoiceTotalAmount($transaction->amount_charged), // invoice total amount
                    new InvoiceTaxAmount($transaction->tax), // invoice tax amount
                ])->render();

                return response()->json([
                    'success' => true,
                    'message' => 'Transaction Response',
                    'data' => [
                        'transaction' => new TransactionResource($transaction),
                        'business_name' => $transaction->company->name,
                        'logo' => $transaction->company->logo ? asset($transaction->company->logo) : null,
                        'vat' => $transaction->company->vat,
                        'is_vat_exempt' => (bool) $transaction->company->is_vat_exempt,
                        'qr_code' => $qr_code,
                    ],

                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid Transaction Request',
                'data' => [],
            ], 400);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Transaction Request',
                'data' => [],
            ], 400);
        }
    }

    /**
     * Store a transaction.
     */
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $this->authorize('create', Transaction::class);

        $transactionService = new TransactionService;

        $transaction = $transactionService->createTransaction(
            branch: $this->loggedInUser->branch,
            data: $request,
            transactionStatus: $request->has('transaction_status') ? TransactionStatus::from($request->transaction_status) : TransactionStatus::Completed,
            transactionOrderSource: TransactionOrderSource::Pos,
            user: $this->loggedInUser,
            diningTableId: $request->dining_table_id,
        );
        // Ensure $transaction is actually a Transaction model instance before proceeding
        if (! ($transaction instanceof Transaction)) {
            return $transaction;
        }

        $transaction->load(['items', 'multipayments', 'waiter', 'discount', 'diningTable', 'branch', 'refundTransactions', 'referenceTransaction', 'user','customer:id,name_ar,name_en']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction has been added successfully.',
            'data' => ['transaction' => new TransactionResource($transaction)],
        ], 201);
    }

    /**
     * Refund a transaction.
     */
    public function refund(RefundTransactionRequest $request, Transaction $transaction, int $refund_type = REFUND_TYPE_FULL): JsonResponse
    {
        $this->authorize('refund', $transaction);

        $transactionService = new TransactionService;
        $refunded_transaction = $transactionService->refundTransaction($request, $transaction, $refund_type);

        if (! ($refunded_transaction instanceof Transaction)) {
            return $refunded_transaction;
        }

        $refunded_transaction->load(['items', 'multipayments', 'waiter', 'discount', 'diningTable', 'branch', 'refundTransactions', 'referenceTransaction', 'user']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction has been refunded.',
            'data' => ['transaction' => new TransactionResource($refunded_transaction)],
        ], 200);
    }

    /**
     * Update a transaction.
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): JsonResponse
    {
        $this->authorize('update', $transaction);

        $transactionService = new TransactionService;
        $transaction = $transactionService->editTransaction(
            data: $request,
            transaction: $transaction,
        );

        // Ensure $transaction is actually a Transaction model instance before proceeding
        if (! $transaction instanceof Transaction) {
            return $transaction;
        }

        $transaction->refresh();
        $transaction->load(['items', 'multipayments', 'waiter', 'discount', 'diningTable', 'branch', 'refundTransactions', 'referenceTransaction', 'user']);

        return response()->json([
            'success' => true,
            'message' => 'Transaction has been edited successfully.',
            'data' => ['transaction' => new TransactionResource($transaction)],
        ], 201);
    }

    /**
     * Show transaction slip.
     *
     * @unauthenticated
     */
    public function showSlip(Company $company, Transaction $transaction): View
    {
        abort_if($company->id !== $transaction->company_id, 404);

        $transaction->load('items', 'discount');

        $qr_code = GenerateQrCode::fromArray([
            // Generate QR Code as per Transaction Details
            new Seller($company->name), // seller name
            new TaxNumber($company->vat), // seller tax number
            new InvoiceDate($transaction->created_at), // invoice date as Zulu ISO8601 @see https://en.wikipedia.org/wiki/ISO_8601
            new InvoiceTotalAmount($transaction->amount_charged), // invoice total amount
            new InvoiceTaxAmount($transaction->tax), // invoice tax amount
        ])->render();

        return view('transaction_slip', [
            'transaction' => $transaction,
            'company' => $company,
            'qr_code' => $qr_code,
        ]);
    }

    /**
     * Get transaction invoice.
     *
     * @unauthenticated
     */
    public function getInvoice(string $md5_string): View
    {
        // $transaction = Transaction::where(DB::raw("MD5(CONCAT(id,'-', type,'-',user_id))"), $md5_string)
        //     ->first();
        $transaction = Transaction::whereRaw("MD5(CONCAT(id,'-', type,'-',user_id)) = ?", [$md5_string])
            ->first();
        abort_unless($transaction !== null, 404);

        return $this->showSlip($transaction->company, $transaction);
    }

    /**
     * Generate slip in pdf.
     *
     * @unauthenticated
     */
    public function generateSlipPDF(Company $company, Transaction $transaction): BinaryFileResponse
    {
        abort_if($company->id !== $transaction->company_id, 404);

        return response()->download($transaction->generatePDF());
    }

    /**
     * Get QR Code.
     */
    public function getQRCode(Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        $qr_code = GenerateQrCode::fromArray([
            // Generate QR Code as per Transaction Details
            new Seller($transaction->company->name), // seller name
            new TaxNumber($transaction->company->vat), // seller tax number
            new InvoiceDate($transaction->created_at), // invoice date
            new InvoiceTotalAmount($transaction->amount_charged), // invoice total amount
            new InvoiceTaxAmount($transaction->tax), // invoice tax amount
        ])->render();

        return response()->json([
            'success' => true,
            'message' => 'QR Code Response.',
            'data' => ['qr_code' => $qr_code],
        ], 200);
    }

    /**
     * Get QR Code Base64.
     */
    public function getQRCodeBase64(Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        $qr_code_base64 = GenerateQrCode::fromArray([
            // Generate QR Code as per Transaction Details
            new Seller($transaction->company->name), // seller name
            new TaxNumber($transaction->company->vat), // seller tax number
            new InvoiceDate($transaction->created_at), // invoice date
            new InvoiceTotalAmount($transaction->amount_charged), // invoice total amount
            new InvoiceTaxAmount($transaction->tax), // invoice tax amount
        ])->toBase64();

        return response()->json([
            'success' => true,
            'message' => 'QR Code Base64 Response.',
            'data' => ['qr_code_base64' => $qr_code_base64],
        ], 200);
    }

    /**
     * Send to Odoo.
     */
    public function sendToOdoo(Transaction $transaction): JsonResponse
    {
        $this->authorize('view', $transaction);

        OdooTransactionCreated::dispatchIf(
            $this->loggedInUser->company->hasOdooIntegration() && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists(),
            $transaction
        );

        return response()->json([
            'success' => true,
            'message' => 'Transaction is being sent to Odoo.',
            'data' => [],
        ], 200);
    }

    /**
     * Export transactions in excel document.
     *
     * @return BinaryFileResponse|JsonResponse
     */
    public function export()
    {
        $this->authorize('viewAny', Transaction::class);

        return Excel::download(new TransactionsExport, 'transactions.xlsx');
    }

    protected function roundOff(float $number): float
    {
        $precision = 8;
        if ($this->loggedInUser->company->round_off) {
            $precision = 2;
        }

        return round($number, $precision);
    }

     /**
     * Store a sale invoice.
     */
    public function createSaleInvoice(StoreSaleInvoiceRequest $request): JsonResponse
    {
        $this->authorize('createSaleInvoice', Transaction::class);
        $transactionService = new TransactionService;
        $transactionStatus = match($request->sale_invoice_status) {
            SALE_INVOICE_STATUS_ISSUE => TransactionStatus::InProgress,
            SALE_INVOICE_STATUS_DRAFT => TransactionStatus::Pending,
            default => TransactionStatus::Pending,
        };
        // Create the transaction using the service
        $transaction = $transactionService->createTransaction(
            branch: $this->loggedInUser->branch,
            data: $request,
            transactionStatus: $transactionStatus,
            transactionOrderSource: TransactionOrderSource::Pos,
            user: $this->loggedInUser,
        );

        // Ensure $transaction is a Transaction model instance before proceeding
        if (! ($transaction instanceof Transaction)) {
            return $transaction;
        }

        $transactionService->deductAddonBalance($this->loggedInUser->company, AddonName::A4SalesInvoice->value, BalanceDeductionType::A4SalesInvoice);
        // Load related models
        $transaction->load(['items', 'discount', 'branch', 'user', 'customer:id,name_ar,name_en']);

        // Return the response
        return response()->json([
            'success' => true,
            'message' => 'Sale Invoice has been added successfully.',
            'data' => ['saleInvoice' => new SaleInvoiceResource($transaction)],
        ], 201);
    }
   /**
     * Display a listing of the sale Invoice resource.
     */
    public function getInvoices(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Transaction::class);
    
        // Base query based on user type
        if ($this->loggedInUser->type === USER_TYPE_BUSINESS_OWNER) {
            $transactions = Transaction::where('company_id', $this->loggedInUser->company_id)->with('user');
        } else {
            $transactions = Transaction::where('branch_id', $this->loggedInUser->branch_id)
                ->when(auth()->user()->is_waiter, function ($query): void {
                    $query->where(function ($q): void {
                        $q->where('user_id', auth()->id())
                            ->orWhere('waiter_id', auth()->id());
                    });
                })
                ->with('user');
        }
    
        // Filter by date range
        if ($request->search_start_date && $request->search_end_date) {
            $start_date = Carbon::parse($request->search_start_date)->toDateTimeString();
            $end_date = Carbon::parse($request->search_end_date)->toDateTimeString();
            $transactions = $transactions->where('created_at', '>=', $start_date)
                ->where('created_at', '<=', $end_date);
        }
    
        // Filter by is_refunded
        if (in_array($request->is_refunded, ['0', '1'])) {
            $transactions = $transactions->where('is_refunded', boolval($request->is_refunded));
        }
    
        // Filter by transaction type
        if (in_array($request->type, ['1', '2', '3', '4', '5'])) {
            $transactions = $transactions->where('type', intval($request->type));
        }
    
        // Filter by sale_invoice_status
        if ($request->filled('sale_invoice_status')) {
            $transactions = $transactions->where('sale_invoice_status', $request->sale_invoice_status);
        }
        if (in_array($request->is_expired, ['0', '1'])) {
            $now = Carbon::now()->toDateTimeString();
            if ($request->is_expired === '0') {
                $transactions = $transactions->where('invoice_due_date', '>=', $now);
            } elseif ($request->is_expired === '1') {
                $transactions = $transactions->where('invoice_due_date', '<', $now);
            }
        }
          // Filter by is_payment_late (only when sale_invoice_status is 4)
        if ($request->sale_invoice_status == '4' && in_array($request->is_payment_late, ['0', '1'])) {
            if ($request->is_payment_late === '1') {
                $transactions = $transactions->whereColumn('invoice_due_date', '<=', 'created_at');
            } elseif ($request->is_payment_late === '0') {
                $transactions = $transactions->whereColumn('invoice_due_date', '>', 'created_at');
            }
        }
        // Filter by transaction status
        if ($request->filled('status')) {
            $transactions = $transactions->where('status', $request->status);
        }
    
        // Include relationships and additional filters
        $transactions = $transactions->with(['items', 'waiter', 'discount', 'diningTable', 'branch', 'refundTransactions', 'referenceTransaction', 'user', 'customer'])
            ->when($request->filled('filter_by_branch'), function ($query) use ($request): void {
                $query->whereHas('branch', function ($q) use ($request): void {
                    $q->where('name', $request->filter_by_branch);
                });
            })
            ->whereNotNull('sale_invoice_status')
            ->latest()
            ->paginate($request->pageSize ?? 15);
    
        // Return JSON response with paginated data
        return response()->json([
            'success' => true,
            'message' => 'Sale Invoice List Response',
            'data' => [
                'saleInvoices' => SaleInvoiceResource::collection($transactions),
                'pagination' => [
                    'total' => $transactions->total(),
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total_pages' => ceil($transactions->total() / $transactions->perPage()),
                    'has_more_pages' => $transactions->hasMorePages(),
                    'next_page_url' => $transactions->nextPageUrl(),
                    'previous_page_url' => $transactions->previousPageUrl(),
                ],
            ],
        ], 200);
    }
    
      /**
     * Display sale Invoice resource.
     */
    public function showSaleInvoice(int $transactionId): JsonResponse
    {
        $transaction = Transaction::where('id', $transactionId)
            ->whereNotNull('sale_invoice_status')
            ->with(['items', 'waiter', 'user', 'customer'])
            ->first();
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found or Sale Invoice Status is null',
            ], 404);
        }
        $this->authorize('view', $transaction);
        return response()->json([
            'success' => true,
            'message' => 'Sale Invoice Response',
            'data' => [
                'saleInvoice' => new SaleInvoiceResource($transaction),
            ],
        ], 200);
    }

       /**
     * Update sale invoice status to Issue.
     */
    public function saleInvoiceStatusUpdate(Request $request, int $transactionId): JsonResponse
    {
        $this->authorize('createSaleInvoice', Transaction::class);
    
        // Retrieve the transaction by its ID
        $transaction = Transaction::find($transactionId);
    
        // Check if the transaction exists
        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found.',
            ], 404);
        }
    
        // Check if the current sale_invoice_status is not SALE_INVOICE_STATUS_DRAFT
        if ($transaction instanceof Transaction && $transaction->sale_invoice_status !== SALE_INVOICE_STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot change this transaction status.',
            ], 403);
        }
         $company=$transaction->company;
        // Create an instance of TransactionService
        $transactionService = new TransactionService();
        $transaction->sale_invoice_status = SALE_INVOICE_STATUS_ISSUE;
        $transaction->status = TransactionStatus::InProgress;
        // Generate and assign the new invoice number
        $transaction->invoice_number = $transactionService->generateInvoiceNumber($company);
        $transaction->create_invoice_date = now();
        $transaction->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Sale Invoice Status Updated.',
        ], 200);
    }
      /**
     * Register Payment againts sale invoice.
     */
    public function registerSaleInvoicePayment(RegisterSaleInvoicePaymentRequest $request): JsonResponse
    {
        // Authorize the action
        $this->authorize('createSaleInvoice', Transaction::class);
    
        // Retrieve the transaction
        /** @var Transaction $transaction */
        $transaction = Transaction::findOrFail($request->transaction_id);
        $chargedAmount = floatval($transaction->amount_charged);
      
        // Sum existing payments
        $existingPaymentsSum = SaleInvoicePayment::where('transaction_id', $transaction->id)->sum('payment');
        $existingPayment = floatval($existingPaymentsSum);
       
        // Define a tolerance level
        $tolerance = 0.01;
        // Check if the existing payments match the charged amount with tolerance
        if (abs($chargedAmount - $existingPayment) <= $tolerance) {
            // Fully paid
            $transaction->sale_invoice_status = SALE_INVOICE_STATUS_PAID;
            $transaction->status = TransactionStatus::Completed;
            $transaction->cash_collected = floatval($existingPaymentsSum);
            $transaction->created_at = now();
        }
    
        // Get the new payment from the request
        $newPayment = floatval($request->payment);
    
        // Calculate total payments (existing + new)
        $totalPayments = floatval($existingPaymentsSum + $newPayment);
        // Check if the total payments exceed the charged amount
        if ($totalPayments > $chargedAmount) {
            return response()->json([
                'success' => false,
                'message' => 'You have exceeded the payment amount.',
            ], 422);
        }
    
        // Register the new payment
        $saleInvoicePayment = SaleInvoicePayment::create([
            'transaction_id' => $transaction->id,
            'payment' => $newPayment,
            'payment_method'=>$request->payment_method,
            'created_by' => $this->loggedInUser->id,
        ]);
    
        // Update transaction status based on total payments
        if ($chargedAmount > $totalPayments) {
            // Partial payment
            $transaction->sale_invoice_status = SALE_INVOICE_STATUS_PARTIALPAID;
        } elseif (abs($chargedAmount - $totalPayments) <= $tolerance) {
            // Fully paid
            $transaction->sale_invoice_status = SALE_INVOICE_STATUS_PAID;
            $transaction->status = TransactionStatus::Completed;
            $transaction->cash_collected = $totalPayments;
            $transaction->created_at = now();
        }
    
        // Save the updated transaction
        $transaction->save();
    
        // Return a success response with the created payment resource
        return response()->json([
            'success' => true,
            'message' => 'Payment registered successfully.',
            'data' => [
                'saleInvoicePayment' => new SaleInvoicePaymentResource($saleInvoicePayment),
            ],
        ], 200);
    }
    
      /**
     * get Payments againts sale invoice.
     */
    public function getSaleInvoicePayment(Request $request,int $transaction_id): JsonResponse
    {
        // // Authorize the action
        // $this->authorize('createSaleInvoice', Transaction::class);
    
        // Fetch sale invoice payments associated with the given transaction_id
        $saleInvoicePayments = SaleInvoicePayment::where('transaction_id', $transaction_id)->get();
    
        // Return a success response with the sale invoice payments resource
        return response()->json([
            'success' => true,
            'message' => 'Sale Invoice Payments.',
            'data' => [
                'saleInvoicePayments' => SaleInvoicePaymentResource::collection($saleInvoicePayments),
            ],
        ], 200);
    }
    
    
    
    
    
}
