<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\Customer\CustomerResource;
use App\Http\Resources\TransactionResource;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Transaction;
use App\Enums\TransactionStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{

    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }
      /**
     * Display a listing of Customer.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Customer::class);
    
        // Start building the query for customers
        $query = Customer::with('city', 'state')
            ->where('company_id', $this->loggedInUser->company_id)
            ->latest();
    
        // Apply filters based on the request parameters
        if ($request->has('user_type')) {
            $query->where('user_type', $request->input('user_type'));
        }
    
        if ($request->has('phone')) {
            $query->where('phone', 'like', '%' . $request->input('phone') . '%');
        }
    
        if ($request->has('state_id')) {
            $query->where('state_id', $request->input('state_id'));
        }
    
        if ($request->has('city_id')) {
            $query->where('city_id', $request->input('city_id'));
        }
    
        // Get the per_page value from the request, default to 15 if not provided
        $perPage = $request->input('per_page', 30);
    
        // Check if the request is asking for all customers (perPage == -1)
        if ($perPage == -1) {
            // Get all customers without pagination
            $customers = $query->get();
    
            // Return JSON response without pagination details
            return response()->json([
                'success' => true,
                'message' => 'Customer List Response',
                'data' => [
                    'customers' => CustomerResource::collection($customers),
                    'pagination' => null,  // No pagination details when all customers are fetched
                ],
            ]);
        } else {
            // Paginate the customers
            $customers = $query->paginate($perPage);
    
            // Return the JSON response with pagination information
            return response()->json([
                'success' => true,
                'message' => 'Customer List Response',
                'data' => [
                    'customers' => CustomerResource::collection($customers),
                    'pagination' => [
                        'total' => $customers->total(),
                        'current_page' => $customers->currentPage(),
                        'per_page' => $customers->perPage(),
                        'total_pages' => ceil($customers->total() / $customers->perPage()),
                        'has_more_pages' => $customers->hasMorePages(),
                        'next_page_url' => $customers->nextPageUrl(),
                        'previous_page_url' => $customers->previousPageUrl(),
                    ],
                ],
            ]);
        }
    }
    
    
    
       /**
     * Display a Specific Customer.
     */
    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);
   
        $customer->load('city', 'state');
        // Return the JSON response with the customer resource
        return response()->json([
            'success' => true,
            'message' => 'Customer Resource',
            'data' => [
                'customer' => new CustomerResource($customer),
            ],
        ], 200);
    }
       /**
     * store newly customer.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);
        // Create a new customer using the validated data from the request
        $customer = Customer::create(array_merge(
            $request->validated(),
            [
                'created_by' => $this->loggedInUser->id,
                'company_id' => $this->loggedInUser->company_id,
            ]
        ));
    
        return response()->json([
            'success' => true,
            'message' => 'Customer Added Successfully!',
            'data' => [
                'customer' => new CustomerResource($customer),
            ],
        ], 201);
    }
    
     /**
     * Delete the specified Customer .
     */
    public function destroy(Customer $customer): JsonResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer Deleted Successfully!',
            'data' => [],
        ], 201);
    }
        /**
     * Update the specified Customer .
     */
    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        // Update the customer with validated data
        $customer->update($request->validated());

        // Return the JSON response with the updated customer resource
        return response()->json([
            'success' => true,
            'message' => 'Customer Updated Successfully!',
            'data' => [
                'customer' => new CustomerResource($customer),
            ],
        ], 200);
    }
    public function customerTransactions(Request $request, int $customerId): JsonResponse
    {

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
        // Apply the filter for customer_id
        $transactions->where('customer_id', $customerId);
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
     * Display a listing of conciseCustomers.
     */
    public function conciseCustomers(Request $request): JsonResponse
    {
        // Select only the specific columns you need
        $customers = Customer::select('id', 'name_ar', 'name_en', 'vat', 'phone')->get();
    
        // Return the JSON response with the selected fields
        return response()->json([
            'success' => true,
            'message' => 'Customer List Response',
            'data' => [
                'customers' => $customers,
            ],
        ]);
    }
    
}
