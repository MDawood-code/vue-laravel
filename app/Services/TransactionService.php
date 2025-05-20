<?php

namespace App\Services;

use App\Models\CompanyAddon;
use App\Enums\AddonName;
use App\Enums\BalanceDeductionType;
use App\Enums\TransactionOrderSource;
use App\Enums\TransactionStatus;
use App\Events\OdooTransactionCreated;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockResource;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CustomFeature;
use App\Models\DiningTable;
use App\Models\Discount;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Carbon\Carbon;
use DateTimeZone;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TransactionService
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Summary of index
     *
     * @return Builder<Transaction>
     */
    public function index(Request $request): Builder
    {
        if ($this->loggedInUser->type === USER_TYPE_BUSINESS_OWNER) {
            $transactions = Transaction::where('company_id', $this->loggedInUser->company_id)->with('user');
        } else {
            $transactions = Transaction::where('branch_id', $this->loggedInUser->branch_id)->with('user');
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
        if (in_array($request->type, ['1', '2', '3', '4'])) {
            $transactions = $transactions->where('type', intval($request->type));
        }

        return $transactions->with(['items', 'multipayments'])
            ->when(request('filter_by_branch'), function ($query): void {
                $query->whereHas('branch', function ($q): void {
                    $q->where('name', request('filter_by_branch'));
                });
            })
            ->latest();
    }

    public function createTransaction(Branch $branch, Request $data, TransactionStatus $transactionStatus, TransactionOrderSource $transactionOrderSource, ?User $user = null, ?int $diningTableId = null): Transaction|JsonResponse
    {
        $this->validateMultipayments($data);
        $company = $branch->company;
        try {
            DB::beginTransaction();
            $items = $this->getItems($data);
            $discount = $this->getDiscount($company, $data);
            $this->deductDailySubscriptionBalance($company);
            $total_tax = 0;
            $total_amount = 0;
            [$items_list, $total_amount, $total_tax] = $this->getItemsList($items, $total_tax, $total_amount, $company);

            [$total_amount, $discount_amount, $total_tax] = $this->applyDiscount($data, $discount, $total_tax, $total_amount);

            // Decrease products stock
            if (hasActiveStockAddon($company->owner)) {
                $this->decreaseProductStock($branch, $items_list);
                $this->deductAddonBalance($company, AddonName::Stock->value, BalanceDeductionType::Stock);
            }

            if ($transactionStatus === TransactionStatus::Completed) {
                $this->validateCashTransaction($data, $total_amount);
            }

            $transaction = $this->createNewTransaction($user, $branch, $data, $total_amount, $discount_amount, $total_tax, $items_list, $transactionStatus, $transactionOrderSource, $diningTableId);

            if ($transactionStatus === TransactionStatus::Completed) {
                $this->handleMultipayments($data, $transaction, $total_amount);
            }

            $this->dispatchOdooEvent($transaction, $transactionStatus);

            DB::commit();

            return $transaction;
        } catch (Exception $e) {
            DB::rollback();
            Log::emergency('Transaction creation failed: ', [$e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Transaction failed: '.$e->getMessage(),
            ], 500);
        }

    }

    public function generateUid(Company $company, ?string $branch_code): string
    {
        // Generate UID
        $last_transaction_id = Transaction::select('uid')
            ->where('company_id', $company->id)
            ->whereNull('refunded_transaction_id')
            ->latest()
            ->first();

        if ($last_transaction_id) {
            $temp = explode('-', $last_transaction_id->uid);
            $last_uid = (int) $temp[count($temp) - 1];
        } else {
            $last_uid = 0;
        }

        $uid = $company->code.'-SO-';
        if ($branch_code !== '' && $branch_code !== '0') {
            $uid .= $branch_code.'-';
        }

        return $uid.Str::padLeft(strval($last_uid + 1), 5, '0');
    }

    public function refundTransaction(Request $request, Transaction $transaction, int $refund_type = REFUND_TYPE_FULL): Transaction|JsonResponse
    {
        try {
            DB::beginTransaction();
            $refunded_transaction = $transaction->replicate();

            if ($refund_type === REFUND_TYPE_PARTIAL) {
                $refunded_items_list = $this->getItemsToPartialRefund($request, $transaction, $refunded_transaction);
            } else {
                $refunded_items_list = $this->getItemsToFullRefund($transaction, $refunded_transaction);
            }

            // Update products stock
            if (hasActiveStockAddon($transaction->company->owner)) {
                $this->updateProductStock($transaction->branch, $refunded_items_list);
            }

            $this->updateOriginalTransaction($transaction);
            $this->setRefundedTransactionDetails($request, $refunded_transaction, $transaction);
            $refunded_transaction->items()->createMany($refunded_items_list);
            $this->dispatchOdooEvent($refunded_transaction, TransactionStatus::Completed);
            DB::commit();

            return $refunded_transaction;
        } catch (Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Transaction refund failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function editTransaction(Request $data, Transaction $transaction): Transaction|JsonResponse
    {
        throw_unless($this->canEditTransaction($transaction), new Exception('Transaction cannot be edited in the current status.'));

        $this->validateMultipayments($data);
        $company = $transaction->company;
        try {
            DB::beginTransaction();
            $items = $this->getItems($data);
            $discount = $this->getDiscount($company, $data);

            $this->deductDailySubscriptionBalance($company);

            $total_tax = 0;
            $total_amount = 0;
            [$items_list, $total_amount, $total_tax] = $this->getItemsList($items, $total_tax, $total_amount, $company);
            [$total_amount, $discount_amount, $total_tax] = $this->applyDiscount($data, $discount, $total_tax, $total_amount);

            if ($data->has('transaction_status') && TransactionStatus::from($data->transaction_status) === TransactionStatus::Completed) {
                $this->validateCashTransaction($data, $total_amount);
            }

            $transaction = $this->updateTransactionDetails($transaction, $data, $total_amount, $discount_amount, $total_tax, $items_list);

            if ($data->has('transaction_status') && TransactionStatus::from($data->transaction_status) === TransactionStatus::Completed) {
                $this->handleMultipayments($data, $transaction, $total_amount);
            }

            $this->dispatchOdooEvent($transaction, $transaction->status);

            DB::commit();

            return $transaction;
        } catch (Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Transaction failed: '.$e->getMessage(),
            ], 500);
        }
    }

    protected function roundOff(float $number, Company $company): float
    {
        $precision = 8;
        if ($company->round_off) {
            $precision = 2;
        }

        return round($number, $precision);
    }

    private function validateMultipayments(Request $data): ?JsonResponse
    {
        if ($data->type == TRANSACTION_TYPE_MULTIPAYMENT) {
            $multipayments = json_decode($data->multipayments);
            foreach ($multipayments as $multipayment) {
                if ((float) $multipayment->amount <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => getTransactionTypeText($multipayment->transaction_type).' amount cannot be zero or less.',
                        'data' => [],
                    ], 400);
                }
            }
        }

        return null;
    }

    /**
     * Summary of getItems
     *
     * @return array<int, array<string, mixed>>
     */
    private function getItems(Request $data): array
    {
        return json_decode((string) $data['items'], true);
    }

    private function getDiscount(Company $company, Request $data): ?Discount
    {
        return Discount::where(['id' => $data['discount_id'], 'company_id' => $company->id])->first();
    }

    /**
     * Summary of getItemsList
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: float}
     */
    private function getItemsList(array $items, float $total_tax, float $total_amount, Company $company): array
    {
        // Product ID as keys
        $items_request = collect($items)->keyBy('id')->toArray();
        // Get All Product IDs
        $products_ids = collect($items_request)->keys()->toArray();
        // Get All Products from DB
        $products = Product::whereIn('id', $products_ids)
            ->where('company_id', $company->id)
            ->get()
            ->keyBy('id');

        $items_list = [];
        foreach ($products as $product_id => $product) {
            $tax = 0;
            // Calculate SubTotal
            $subtotal = ($items_request[$product_id]['qty'] * $items_request[$product_id]['price']);

            // if Taxable, Calculate Tax
            if ($product->is_taxable) {
                $tax = $subtotal * TAX_PERCENTAGE;
                $total_tax += $tax;
                $subtotal += $tax;
            }

            $total_amount += $subtotal;

            $items_list[] = [
                'name' => $product->name,
                'name_en' => $product->name_en,
                'price' => $items_request[$product_id]['price'],
                'quantity' => $items_request[$product_id]['qty'],
                'tax' => $this->roundOff($tax, $company),
                'subtotal' => $this->roundOff($subtotal, $company),
                'category' => $product->category->name,
                'unit' => $product->unit->name,
                'barcode' => $product->barcode,
                'image' => $product->image ? asset(Storage::url($product->image)) : '',
                'product_id' => $product_id,
            ];
        }

        return [
            $items_list,
            $total_amount,
            $total_tax,
        ];
    }

    /**
     * Summary of applyDiscount
     *
     * @return array<int, float>
     */
    private function applyDiscount(Request $data, ?Discount $discount, float $total_tax, float $total_amount): array
    {
        $discount_amount = 0;
        if ($data->discount_id && $discount) {
            $discount_amount = ($discount->discount_percentage / 100.0) * ($total_amount - $total_tax);
            $amount_after_discount_without_tax = $total_amount - $discount_amount - $total_tax;
            $total_tax -= ($discount->discount_percentage / 100.0) * $total_tax;
            $total_amount = $amount_after_discount_without_tax + $total_tax;
        }

        return [
            $total_amount,
            $discount_amount,
            $total_tax,
        ];
    }

    /**
     * Summary of decreaseProductStock
     *
     * @param  array<int, array<string, mixed>>  $items_list
     *
     * @throws Exception
     */
    private function decreaseProductStock(Branch $branch, array $items_list): JsonResponse
    {
        $productIds = collect($items_list)->pluck('product_id');
        $stocks = Stock::whereIn('product_id', $productIds)->where('branch_id', $branch->id)->get()->keyBy('product_id');

        $stocks_list = [];
        $stocks_quantity = [];

        foreach ($items_list as $item) {
            $productId = $item['product_id'];
            if (Product::where('id', $productId)->where('is_stockable', BOOLEAN_TRUE)->exists()) {
                $stock = $stocks->get($productId);
                if (! $stock) {
                    $stocks_list[] = $productId;
                } elseif ($stock->quantity < $item['quantity']) {
                    $stocks_quantity[] = $stock;
                } else {
                    $stock->decrement('quantity', $item['quantity']);
                }
            }
        }

        if ($stocks_list !== []) {
            $products = Product::whereIn('id', $stocks_list)->get();
            return response()->json([
                'success' => false,
                'message' => 'Product Not found in Stock.',
                'data' => ProductResource::collection($products),
            ], 400);
        }

        if ($stocks_quantity !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Cart Quantity is greater than stock quantity.',
                'data' => StockResource::collection($stocks_quantity),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stock updated successfully.'
        ], 200);
    }

    private function validateCashTransaction(Request $data, float $total_amount): ?JsonResponse
    {
        if ($data->type == TRANSACTION_TYPE_CASH && (float) $data->cash_collected < $total_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Cash cannot be less than '.$total_amount,
                'data' => [],
            ], 400);
        }

        return null;
    }

    /**
     * Summary of createNewTransaction
     *
     * @param  array<int, array<string, mixed>>  $items_list
     */
    private function createNewTransaction(?User $user, Branch $branch, Request $data, float $total_amount, float $discount_amount, float $total_tax, array $items_list, TransactionStatus $transactionStatus, TransactionOrderSource $transactionOrderSource, ?int $diningTableId): Transaction
    {

        if ($transactionStatus === TransactionStatus::Completed) {
            $cash_collected = (float) $data->cash_collected;
            $payment_type = $data->type ?? TRANSACTION_TYPE_CASH;
        } else {
            $cash_collected = 0.0;
            $payment_type = TRANSACTION_TYPE_CASH;
        }

        $transaction = new Transaction;
        $transaction->cash_collected = $cash_collected;
        $transaction->amount_charged = $this->roundOff($total_amount, $branch->company);
        $transaction->tax = $this->roundOff($total_tax, $branch->company);
        $transaction->discount_amount = $this->roundOff($discount_amount, $branch->company);
        $transaction->discount_id = $data->discount_id;
        $transaction->tip = (float) $data->tip;
        $transaction->type = $payment_type;
        $transaction->payment_source = $data->payment_source;
        $transaction->reference = $data->reference ?? '';
        $transaction->payment_channel = $data->payment_channel ?? null;
        if ($data->payment_channel === "taptopay") {
            $this->deductAddonBalance($branch->company, AddonName::TapToPay->value, BalanceDeductionType::TapToPay);
        }
        $transaction->buyer_company_name = $data->buyer_company_name ?? '';
        $transaction->buyer_company_vat = $data->buyer_company_vat ?? '';
        $transaction->company_id = $branch->company_id;
        $transaction->branch_id = $branch->id;
        $transaction->user_id = $user?->id;
        if ($data->created_at) {
            $transaction->created_at = Carbon::parse($data->created_at)->toDateTimeString();
        }
        if ($data->customer_id && hasActiveCustomerManagementAddon($branch->company->owner)) {
            $this->deductAddonBalance($branch->company, AddonName::CustomerManagement->value, BalanceDeductionType::CustomerManagement);
            $transaction->customer_id = $data->customer_id;
        }

        // Generate company code
        $branch->company->generateCode();

        $branch_code = $branch->code;

        $transaction->uid = $this->generateUid($branch->company, $branch_code);
        $transaction->order_id = $this->generateOrderId($branch->company_id, false);
        $transaction->status = $transactionStatus;
        $transaction->order_source = $transactionOrderSource;
        if ($transactionOrderSource === TransactionOrderSource::QrOrder) {
            $this->deductAddonBalance($branch->company, AddonName::QrOrdering->value, BalanceDeductionType::QrOrdering);
        }
        if (hasActiveTableManagementAddon($branch->company->owner) && $diningTableId) {
            $transaction->dining_table_id = $diningTableId;
            if (DiningTable::find($diningTableId)?->is_drive_thru) {
                $transaction->customer_name = $data->customer_name;
                $transaction->vehicle_number = $data->vehicle_number;
                $transaction->vehicle_color = $data->vehicle_color;
            }
            $this->deductAddonBalance($branch->company, AddonName::TableManagement->value, BalanceDeductionType::TableManagement);
        }
        if ((hasActiveWaiterManagementAddon($branch->company->owner) || hasActiveJobManagementAddon($branch->company->owner)) && $data->has('waiter_id') && User::where('id', $data->waiter_id)->where('company_id', $branch->company_id)->exists()) {
            $transaction->waiter_id = $data->waiter_id;
            if (hasActiveWaiterManagementAddon($branch->company->owner)) {
                $this->deductAddonBalance($branch->company, AddonName::WaiterManagement->value, BalanceDeductionType::WaiterManagement);
            }
            if (hasActiveJobManagementAddon($branch->company->owner)) {
                $this->deductAddonBalance($branch->company, AddonName::JobManagement->value, BalanceDeductionType::JobManagement);
            }
        }
        // this logic is for Sale Invoice Creation
        if (isset($data->sale_invoice_status)) {
            if ($data->sale_invoice_status == SALE_INVOICE_STATUS_ISSUE) {
                $invoiceNumber = $this->generateInvoiceNumber($branch->company);
                $transaction->invoice_number = $invoiceNumber;
                $transaction->create_invoice_date = now();
            }
            $transaction->sale_invoice_status=$data->sale_invoice_status;
            $transaction->invoice_due_date = isset($data->invoice_due_date) && !empty($data->invoice_due_date)
            ? Carbon::parse($data->invoice_due_date)->setTimezone('UTC')->toDateTimeString()
            : null;
        }
        $transaction->save();
        $transaction->items()->createMany($items_list);

        return $transaction;
    }
  /**
     * Generate a new invoice number.
     */
    public function generateInvoiceNumber(Company $company): string
    {
        // Retrieve the last invoice number for the given company
        $lastInvoice = Transaction::select('invoice_number')
            ->where('company_id', $company->id)
            ->whereNotNull('invoice_number')
            ->orderBy('invoice_number', 'desc')
            ->first();
        // Log the retrieved last invoice number
        Log::info('Last Invoice Record:', ['last_invoice' => $lastInvoice]);

        // Extract the numeric part from the last invoice number
        if ($lastInvoice) {
            // Log the invoice number format
            Log::info('Extracting from Invoice Number:', ['invoice_number' => $lastInvoice->invoice_number]);

            if (preg_match('/INV_(\d+)$/', $lastInvoice->invoice_number, $matches)) {
                $last_invoice_number = (int) $matches[1];
            } else {
                Log::warning('Invalid invoice number format found:', ['invoice_number' => $lastInvoice->invoice_number]);
                $last_invoice_number = 0; // Reset if format is incorrect
            }
        } else {
            $last_invoice_number = 0;
        }

        // Generate the new invoice number with leading zeros
        $invoiceNumber = 'INV_' . Str::padLeft(strval($last_invoice_number + 1), 3, '0');

        // Log the generated invoice number
        Log::info('Generated Invoice Number:', ['invoice_number' => $invoiceNumber]);

        return $invoiceNumber;
    }

    private function handleMultipayments(Request $data, Transaction $transaction, float $total_amount): void
    {
        // Add Transaction Multi Payments Amounts if any
        if ($data->type == TRANSACTION_TYPE_MULTIPAYMENT) {
            $multipayments = json_decode($data->multipayments);
            // Filter to get transaction type card
            // get first element from that array
            $card_payment_amount = 0;
            $card_payment = array_values(array_filter($multipayments, fn ($x): bool => $x->transaction_type == TRANSACTION_TYPE_CREDIT));
            if ($card_payment !== []) {
                $card_payment_amount = $card_payment[0]->amount;
            }

            foreach ($multipayments as $multipayment) {
                $type_amount = (float) $multipayment->amount;
                // if transaction type cash
                // calculate amount that need to be paid in cash
                if ($multipayment->transaction_type == TRANSACTION_TYPE_CASH) {
                    $type_amount = $total_amount - (float) $card_payment_amount;
                }

                $transaction->multipayments()->create([
                    'transaction_type' => $multipayment->transaction_type,
                    'amount' => $type_amount,
                ]);
            }
        }
    }

    private function dispatchOdooEvent(Transaction $transaction, TransactionStatus $transactionStatus): void
    {
        if ($this->shouldDispatchOdooEvent($transactionStatus)) {
            OdooTransactionCreated::dispatch($transaction);
        }
    }

    private function shouldDispatchOdooEvent(TransactionStatus $transactionStatus): bool
    {
        return $transactionStatus === TransactionStatus::Completed
            && $this->loggedInUser->company->hasOdooIntegration()
            && CustomFeature::where('title', 'Odoo Integration')->where('status', true)->exists();
    }

    private function generateOrderId(int|string $companyId, int|bool $isRefunded): string
    {
        // Set the timezone to Saudi Arabia Standard Time (UTC+3)
        $saudiTimezone = new DateTimeZone('Asia/Riyadh');
        $date = now($saudiTimezone)->format('Y-m-d');
        $prefix = $isRefunded ? 'RF' : 'SO';
        $lastOrder = Transaction::where('company_id', $companyId)
            ->whereRaw("DATE(CONVERT_TZ(created_at, '+00:00', '+03:00')) = ?", [$date])
            ->where('order_id', 'like', "{$prefix}%")
            ->orderBy('created_at', 'desc')
            ->first();

        $lastOrderId = $lastOrder?->order_id;
        $number = $lastOrderId ? (int) explode('-', $lastOrderId)[4] + 1 : 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $number);
    }

    private function deductDailySubscriptionBalance(Company $company): ?JsonResponse
    {
        $active_subscription = $company->active_subscription;

        // Check if the active subscription is a daily subscription
        if ($active_subscription->type === PLAN_TYPE_DAILY && !$active_subscription->is_trial) {
            $balance = $company->balance;
            $today = Carbon::now('Asia/Riyadh')->startOfDay();

            // Check if there's no balance deduction for today
            $balanceDeductionToday = $balance->balanceDeductions()
                ->where('deduction_type', BalanceDeductionType::Subscription->value)
                ->where('user_id', auth()->id() ?? $company->owner->id)
                ->where('created_at', '>=', $today->copy()->setTimezone('UTC'))
                ->first();

            if (! $balanceDeductionToday) {
                $daily_required_amount_per_user = $active_subscription->amount / $active_subscription->user_licenses_count + $active_subscription->license_amount - $active_subscription->license_discount;

                throw_if($balance->amount < $daily_required_amount_per_user, new Exception('Insufficient balance for daily use.'));

                // Deduct balance
                $balance->amount -= $daily_required_amount_per_user;
                $balance->save();

                // Add balance deduction entry
                $balance->balanceDeductions()->create([
                    'amount' => $daily_required_amount_per_user,
                    'deduction_type' => BalanceDeductionType::Subscription->value,
                    'company_id' => $company->id,
                    'user_id' => auth()->id() ?? $company->owner->id,
                    'created_at' => Carbon::now()->toDateString(),
                ]);
            }
        }

        return null;
    }

    public function deductAddonBalance(Company $company, string $addonName, BalanceDeductionType $balanceDeductionType): ?JsonResponse
    {
        /** @var CompanyAddon $addon */
        $addon = $company->activeAddons()->whereHas('addon', function ($query) use ($addonName): void {
            $query->where('name', $addonName);
        })->first();

        // Check if the addon is in trial and if the trial has ended
        if ($addon && $company->isAddonInTrial($addon)) {
            return null; // Do not deduct balance if the addon is in trial
        }

        $balance = $company->balance;
        $today = Carbon::now('Asia/Riyadh')->startOfDay();

        // Check if there's no balance deduction for today for this addon
        $balanceDeductionToday = $balance->balanceDeductions()
            ->where('deduction_type', $balanceDeductionType->value)
            ->where('created_at', '>=', $today->copy()->setTimezone('UTC'))
            ->first();

        if (! $balanceDeductionToday) {
            $addonCost = $addon->price - $addon->discount;

            throw_if($balance->amount < $addonCost, new Exception("Insufficient balance for {$addonName} addon."));

            // Deduct balance
            $balance->amount -= $addonCost;
            $balance->save();

            // Add balance deduction entry for the addon
            $balance->balanceDeductions()->create([
                'amount' => $addonCost,
                'deduction_type' => $balanceDeductionType->value,
                'company_id' => $company->id,
                'user_id' => auth()->id() ?? $company->owner->id,
                'created_at' => Carbon::now()->toDateString(),
            ]);
        }

        return null;
    }

    /**
     * Summary of getItemsToPartialRefund
     *
     * @return array<int, array<string, mixed>>
     */
    private function getItemsToPartialRefund(Request $request, Transaction $transaction, Transaction &$refunded_transaction): array
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = json_decode($request->items);
        $total_tax = 0;
        $total_amount = 0;
        $items_request = collect($items)->keyBy('id')->toArray();
        $products_ids = collect($items_request)->keys()->toArray();
        $transaction_items = TransactionItem::whereIn('product_id', $products_ids)
            ->where('transaction_id', $transaction->id)
            ->get()
            ->keyBy('product_id');

        $refunded_items_list = $this->getPartialItemsList($items_request, $transaction_items, $total_tax, $total_amount);

        $this->applyRefundDiscount($refunded_transaction, $total_tax, $total_amount);

        $refunded_transaction->amount_charged = $this->roundOff($total_amount * -1, $this->loggedInUser->company);
        $refunded_transaction->tax = $this->roundOff($total_tax * -1, $this->loggedInUser->company);
        $refunded_transaction->is_refunded = REFUND_TYPE_PARTIAL;

        return $refunded_items_list;
    }

    /**
     * Summary of getPartialItemsList
     *
     * @param  array<int, array<string, mixed>>  $items_request
     * @param Collection<int, TransactionItem> $transaction_items
     * @return array<int, array<string, mixed>>
     */
    private function getPartialItemsList(array $items_request, Collection $transaction_items, float &$total_tax, float &$total_amount): array
    {
        $refunded_items_list = [];
        foreach ($transaction_items as $item) {
            $tax = 0;

            $subtotal = ($items_request[$item->product_id]->qty * $item->price);

            if ($item->tax > 0) {
                $tax = $subtotal * TAX_PERCENTAGE;
                $total_tax += $tax;
                $subtotal += $tax;
            }

            $total_amount += $subtotal;

            $refunded_items_list[] = [
                'name' => $item->name,
                'name_en' => $item->name_en,
                'price' => $item->price * -1,
                'quantity' => $items_request[$item->product_id]->qty,
                'tax' => $tax * -1,
                'subtotal' => $subtotal * -1,
                'category' => $item->category,
                'unit' => $item->unit,
                'barcode' => $item->barcode,
                'image' => $item->image,
                'product_id' => $item->product_id,
            ];
        }

        return $refunded_items_list;
    }

    /**
     * Summary of getItemsToFullRefund
     *
     * @return array<int, array<string, mixed>>
     */
    private function getItemsToFullRefund(Transaction $transaction, Transaction &$refunded_transaction): array
    {
        $refunded_transaction->amount_charged *= -1;
        $refunded_transaction->tax *= -1;
        $refunded_transaction->is_refunded = REFUND_TYPE_FULL;

        $refunded_items_list = [];
        foreach ($transaction->items as $item) {
            $refunded_items_list[] = [
                'name' => $item->name,
                'name_en' => $item->name_en,
                'price' => $item->price * -1,
                'quantity' => $item->quantity,
                'tax' => $item->tax * -1,
                'subtotal' => $item->subtotal * -1,
                'category' => $item->category,
                'unit' => $item->unit,
                'barcode' => $item->barcode,
                'image' => $item->image,
                'product_id' => $item->product_id,
            ];
        }

        return $refunded_items_list;
    }

    private function applyRefundDiscount(Transaction &$refunded_transaction, float &$total_tax, float &$total_amount): void
    {
        if ($refunded_transaction->discount_id && $discount = Discount::where(['id' => $refunded_transaction->discount_id, 'company_id' => $this->loggedInUser->company->id])->first()) {
            $discount_amount = ($discount->discount_percentage / 100.0) * ($total_amount - $total_tax);
            $amount_after_discount_without_tax = $total_amount - $discount_amount - $total_tax;
            $total_tax -= ($discount->discount_percentage / 100.0) * $total_tax;
            $total_amount = $amount_after_discount_without_tax + $total_tax;
            $refunded_transaction->discount_amount = $this->roundOff($discount_amount, $this->loggedInUser->company);
        }
    }

    private function updateOriginalTransaction(Transaction &$transaction): void
    {
        $transaction->is_refunded = BOOLEAN_TRUE;
        $transaction->save();
    }

    private function setRefundedTransactionDetails(Request $request, Transaction &$refunded_transaction, Transaction $transaction): void
    {
        $refunded_transaction->type = TRANSACTION_TYPE_CASH;
        $refunded_transaction->refunded_transaction_id = $transaction->id;

        if ($request->updated_at) {
            $refunded_transaction->updated_at = Carbon::parse($request->updated_at)->toDateTimeString();
        }

        $company_code = $this->loggedInUser->company->code;
        $branch_code = $this->loggedInUser->branch->code;

        $last_refunded_transaction_id = Transaction::select('uid')
            ->where('company_id', $this->loggedInUser->company_id)
            ->whereNotNull('refunded_transaction_id')
            ->latest()
            ->first();

        if ($last_refunded_transaction_id) {
            $temp = explode('-', $last_refunded_transaction_id->uid);
            $last_uid = (int) $temp[count($temp) - 1];
        } else {
            $last_uid = 0;
        }

        $uid = $company_code.'-RF-';
        if ($branch_code) {
            $uid .= $branch_code.'-';
        }
        $uid .= Str::padLeft(strval($last_uid + 1), 5, '0');

        $refunded_transaction->uid = $uid;
        $refunded_transaction->order_id = $this->generateOrderId($transaction->company_id, true);
        $refunded_transaction->odoo_reference_number = null;
        $refunded_transaction->save();
    }

    /**
     * Summary of updateProductStock
     *
     * @param  array<int, array<string, mixed>>  $items_list
     *
     * @throws Exception
     */
    private function updateProductStock(Branch $branch, array $items_list): void
    {
        $productIds = collect($items_list)->pluck('product_id');
        $stocks = Stock::whereIn('product_id', $productIds)->where('branch_id', $branch->id)->get()->keyBy('product_id');

        $stocks_list = [];

        foreach ($items_list as $item) {
            $productId = $item['product_id'];
            if (Product::where('id', $productId)->where('is_stockable', BOOLEAN_TRUE)->exists()) {
                $stock = $stocks->get($productId);

                if (! $stock) {
                    $stocks_list[] = $productId;
                } else {
                    $stock->increment('quantity', $item['quantity']);
                }
            }
        }

        if ($stocks_list !== []) {
            $products = Product::whereIn('id', $stocks_list)->get();
            throw new Exception(json_encode([
                'success' => false,
                'message' => 'Product Not found in Stock.',
                'data' => ProductResource::collection($products),
            ]) ?: 'Error encoding JSON', 400);
        }
    }

    private function canEditTransaction(Transaction $transaction): bool
    {
        return in_array($transaction->status, [TransactionStatus::Pending, TransactionStatus::InProgress]);
    }

    /**
     * Summary of updateTransactionDetails
     *
     * @param  array<int, array<string, mixed>>  $items_list
     */
    private function updateTransactionDetails(Transaction $transaction, Request $data, float $total_amount, float $discount_amount, float $total_tax, array $items_list): Transaction
    {
        if ($data->has('transaction_status') && TransactionStatus::from($data->transaction_status) === TransactionStatus::Completed) {
            $cash_collected = (float) $data->cash_collected;
            $payment_type = $data->type ?? TRANSACTION_TYPE_CASH;
        } else {
            $cash_collected = 0.0;
            $payment_type = TRANSACTION_TYPE_CASH;
        }
        $company = $transaction->company;
        $transaction->cash_collected = $cash_collected;
        $transaction->amount_charged = $this->roundOff($total_amount, $company);
        $transaction->tax = $this->roundOff($total_tax, $company);
        $transaction->discount_amount = $this->roundOff($discount_amount, $company);
        $transaction->discount_id = $data->discount_id;
        $transaction->tip = (float) $data->tip;
        $transaction->type = $payment_type;
        $transaction->payment_source = $data->payment_source ?? $transaction->payment_source;
        $transaction->reference = $data->reference ?? $transaction->reference;
        $transaction->payment_channel = $data->payment_channel ?? $transaction->payment_channel;
        $transaction->buyer_company_name = $data->buyer_company_name ?? '';
        $transaction->buyer_company_vat = $data->buyer_company_vat ?? '';
        if ($transaction->dining_table_id && Auth::check()) {
            $transaction->user_id = Auth::id();
        }
        if ($data->created_at) {
            $transaction->created_at = Carbon::parse($data->created_at)->toDateTimeString();
        }

        $transaction->status = $data->has('transaction_status') ? TransactionStatus::from($data->transaction_status) : $transaction->status;
        if (hasActiveTableManagementAddon($company->owner)) {
            $transaction->dining_table_id = $data->dining_table_id ?? $transaction->dining_table_id;
        }
        if ((hasActiveWaiterManagementAddon($company->owner) || hasActiveJobManagementAddon($company->owner)) && $data->has('waiter_id') && User::where('id', $data->waiter_id)->where('company_id', $company->id)->exists()) {
            $transaction->waiter_id = $data->waiter_id;
        }

        $transaction->save();
        $this->updateTransactionItems($transaction, $items_list);

        return $transaction;
    }

    /**
     * Summary of updateTransactionItems
     *
     * @param  array<int, array<string, mixed>>  $updatedItems
     */
    private function updateTransactionItems(Transaction $transaction, array $updatedItems): void
    {
        // Logic to add or remove items based on the $updatedItems array
        // Example:
        // 1. Remove existing items not present in $updatedItems
        $existingItemIds = $transaction->items->pluck('product_id')->toArray();
        $updatedItemIds = collect($updatedItems)->pluck('product_id')->toArray();
        $itemsToRemove = array_diff($existingItemIds, $updatedItemIds);
        $itemToDelete = $transaction->items()->whereIn('product_id', $itemsToRemove);
        $stockToUpdate = $itemToDelete->get();
        $refunded_items_list = [];
        // Update products stock
        if ($stockToUpdate->isNotEmpty()) {
            foreach ($stockToUpdate as $item) {
                $refunded_items_list[] = [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                ];
            }

            if (hasActiveStockAddon($transaction->company->owner)) {
                $this->updateProductStock($transaction->branch, $refunded_items_list);
            }
        }

        $itemToDelete->delete();

        // 2. Add new items from $updatedItems
        $itemsToAddIds = array_diff($updatedItemIds, $existingItemIds);
        $decreaseStock = collect($updatedItems)->whereIn('product_id', $itemsToAddIds);
        $transaction->items()->createMany(collect($updatedItems)->whereIn('product_id', $itemsToAddIds));
        $transaction->items()->whereIn('product_id', $itemsToAddIds);

        $decrease_items_list = [];
        if ($decreaseStock !== null) {
            foreach ($decreaseStock as $item) {
                $decrease_items_list[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ];
            }

            // Decrease products stock
            if (hasActiveStockAddon($transaction->company->owner)) {
                $this->decreaseProductStock($transaction->branch, $decrease_items_list);
            }
        }
        $itemsToUpdate = array_intersect($updatedItemIds, $existingItemIds);
        $itemsToUpdate = collect($updatedItems)->whereIn('product_id', $itemsToUpdate);
        // 3. Update existing items if necessary
        $decreaseItemStock = [];
        $increaseItemStock = [];
        if ($itemsToUpdate->isNotEmpty()) {
            foreach ($itemsToUpdate as $item) {
                $updateTransaction = $transaction->items()->where('product_id', $item['product_id']);
                $getTransaction = $updateTransaction->get();

                $quantityDiff = $item['quantity'] - $getTransaction[0]->quantity;
                if ($quantityDiff > 0) {
                    $decreaseItemStock[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => abs($quantityDiff),
                    ];
                } else {
                    $increaseItemStock[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => abs($quantityDiff),
                    ];
                }
                $updateTransaction->update($item);
            }
            if (hasActiveStockAddon($transaction->company->owner)) {
                $this->decreaseProductStock($transaction->branch, $decreaseItemStock);
                $this->updateProductStock($transaction->branch, $increaseItemStock);
            }
        }
    }
}
