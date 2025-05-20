<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\UploadedFile;
use App\Http\Controllers\Controller;
use App\Http\Requests\HelpdeskTicket\UpdateHelpdeskTicketRequest;
use App\Http\Requests\Reseller\BankDetailRequest;
use App\Http\Requests\Reseller\ChangeStatusRequest;
use App\Http\Requests\Reseller\PayoutHistoryRequest;
use App\Http\Requests\Reseller\ResellerLevelConfigurationRequest;
use App\Http\Requests\Reseller\StoreResellerRequest;
use App\Http\Requests\Reseller\UpdateResellerProfileRequest;
use App\Http\Requests\Reseller\UpdateResellerRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\HelpdeskTicketCollection;
use App\Http\Resources\HelpdeskTicketResource;
use App\Http\Resources\ResellerBankDetailResource;
use App\Http\Resources\ResellerLevelConfigurationResource;
use App\Http\Resources\ResellerPayoutHistoryResource;
use App\Http\Resources\ResellerResource;
use App\Http\Traits\FileUploadTrait;
use App\Models\Company;
use App\Models\HelpdeskTicket;
use App\Models\PayoutDetail;
use App\Models\ResellerBankDetail;
use App\Models\ResellerLevelConfiguration;
use App\Models\ResellerPayoutHistory;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Image;

class ResellerController extends Controller
{
    use FileUploadTrait;

    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Display a listing of Reseller.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);
        $status = $request->has('status') ? $request->integer('status') : null;

        $query = User::with('resellerPayoutHistory')
            ->where('type', USER_TYPE_RESELLER);

        if (! is_null($status)) {
            $query->where('status', $status);
        }

        $reseller = $query->latest()->paginate(15);

        return response()->json([
            'success' => true,
            'message' => 'Reseller List Response',
            'data' => [
                'resellers' => ResellerResource::collection($reseller),
                'pagination' => [
                    'total' => $reseller->total(),
                    'current_page' => $reseller->currentPage(),
                    'per_page' => $reseller->perPage(),
                    'total_pages' => ceil($reseller->total() / $reseller->perPage()),
                    'has_more_pages' => $reseller->hasMorePages(),
                    'next_page_url' => $reseller->nextPageUrl(),
                    'previous_page_url' => $reseller->previousPageUrl(),
                ],
            ],
        ]);
    }

    /**
     * Show the specified reseller.
     */
    public function show(User $reseller): JsonResponse
    {
        $this->authorize('adminDashboard', $reseller);

        // Load the resellerPayoutHistory relation
        $reseller->load('resellerPayoutHistory');

        return response()->json([
            'success' => true,
            'message' => 'Reseller Resource',
            'data' => [
                'reseller' => new ResellerResource($reseller),
            ],
        ], 200);
    }

    /**
     * store newly resellers.
     */
    public function store(StoreResellerRequest $request): JsonResponse
    {

        $this->authorize('adminDashboard', User::class);
        $data = $request->safe()->except(['password', 'is_active']);
        // Extract first name from the request and get the first 5 characters
        $firstName = $request->input('first_name');
        $firstNamePart = substr((string) $firstName, 0, 5);

        // Generate a random 4-digit number
        $randomNumber = mt_rand(1000, 9999);
        $currentDate = Carbon::now()->toDateString();
        // Combine first name part and random number to form reseller number
        $resellerNumber = $firstNamePart.$randomNumber;
        $data += [
            'password' => bcrypt($request->password),
            'status' => RESELLER_STATUS_KYC,
            'app_config' => '{"direction":"ltr", "allowEditablePrice": true}',
            'type' => USER_TYPE_RESELLER,
            'reseller_number' => $resellerNumber,
            'reseller_level' => 'Basic',
            'reseller_level_change_at' => $currentDate,
        ];
        // Handling the User photo upload
        if ($request->hasFile('user_photo_id')) {

            // Get File
            /** @var UploadedFile $image */
            $image = $request->file('user_photo_id');
            // Generate Random Name
            $file_name = Str::random(14).'_'.time().'.'.$image->extension();
            // Set File Path
            $access_path = 'public/user_images/'.$this->loggedInUser->id;
            $file_path = storage_path('app/'.$access_path);
            // Create directory if not exists
            if (! is_dir($file_path)) {
                mkdir($file_path, 0775, true);
            }

            // Create Image Object
            $img = Image::make($image->path());

            // Optional: Resize without cropping
            // $img->resize(500, null, function ($constraint) {
            //     $constraint->aspectRatio();
            //     $constraint->upsize();
            // });

            // Save the image as it is or with optional resizing
            $img->save($file_path.'/'.$file_name);

            // Save Path to User Photo ID
            $user_photo_id = $access_path.'/'.$file_name;
            $data['user_photo_id'] = $user_photo_id;
        }

        // Handling the company registration document upload
        if ($request->hasFile('company_registration_document')) {
            // Get the file
            /** @var UploadedFile $document */
            $document = $request->file('company_registration_document');

            // Generate a random file name
            $file_name = Str::random(14).'_'.time().'.'.$document->extension();

            // Set the file path
            $access_path = 'public/reseller_company_registration/'.$this->loggedInUser->id;
            $file_path = storage_path('app/'.$access_path);

            // Create the directory if it doesn't exist
            if (! is_dir($file_path)) {
                mkdir($file_path, 0775, true);
            }

            // Move the document to the designated path
            $document->move($file_path, $file_name);

            // Save the path to the company_registration_document
            $company_registration_document_path = $access_path.'/'.$file_name;

            // Add the company_registration_document path to the data array
            $data['company_registration_document'] = $company_registration_document_path;
        }
        $reseller = User::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Reseller Added Successfully!',
            'data' => [
                'reseller' => new ResellerResource($reseller),
            ],
        ], 201);
    }

    /**
     * Delete the specified reseller .
     */
    public function destroy(User $reseller): JsonResponse
    {
        $this->authorize('adminDashboard', $reseller);

        $reseller->delete();

        return response()->json([
            'success' => true,
            'message' => 'Reseller Deleted Successfully!',
            'data' => [],
        ], 201);
    }

    /**
     * Update the specified reseller.
     */
    public function update(UpdateResellerRequest $request, User $reseller): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);

        $data = $request->safe()->except(['password', 'is_active']);

        if ($request->has('password') && ! empty($request->password)) {
            $data['password'] = bcrypt($request->password);
        }

        if ($request->has('status')) {
            $data['status'] = boolval($request->status);
        }

        if ($request->has('user_type') && $request->user_type === 'individual') {
            $data['company_registration_document'] = null;
            $data['reseller_company_name'] = null;
        }

        // Handling the User photo update
        if ($request->file('user_photo_id')) {
            // Delete the old photo if it exists
            if ($reseller->user_photo_id && Storage::exists($reseller->user_photo_id)) {
                Storage::delete($reseller->user_photo_id);
            }

            // Get new File
            /** @var UploadedFile $image */
            $image = $request->file('user_photo_id');
            // Generate Random Name
            $file_name = Str::random(14).'_'.time().'.'.$image->extension();
            // Set File Path
            $access_path = 'public/user_images/'.$this->loggedInUser->id;
            $file_path = storage_path('app/'.$access_path);
            // Create directory if not exists
            if (! is_dir($file_path)) {
                mkdir($file_path, 0775, true);
            }
            // Create Image Object
            $img = Image::make($image->path());
            // Resize, Crop and Save
            $img->fit(500)->save($file_path.'/'.$file_name);
            // Save Path to Product
            $user_photo_id = $access_path.'/'.$file_name;
            $data['user_photo_id'] = $user_photo_id;
        }

        // Handling the company registration document update
        if ($request->file('company_registration_document')) {
            // Delete the old document if it exists
            if ($reseller->company_registration_document && Storage::exists($reseller->company_registration_document)) {
                Storage::delete($reseller->company_registration_document);
            }

            // Get the new file
            /** @var UploadedFile $document */
            $document = $request->file('company_registration_document');

            // Generate a random file name
            $file_name = Str::random(14).'_'.time().'.'.$document->extension();

            // Set the file path
            $access_path = 'public/reseller_company_registration/'.$this->loggedInUser->id;
            $file_path = storage_path('app/'.$access_path);

            // Create the directory if it doesn't exist
            if (! is_dir($file_path)) {
                mkdir($file_path, 0775, true);
            }

            // Move the document to the designated path
            $document->move($file_path, $file_name);

            // Save the path to the company_registration_document
            $company_registration_document_path = $access_path.'/'.$file_name;

            // Add the company_registration_document path to the data array
            $data['company_registration_document'] = $company_registration_document_path;
        }

        // Update the reseller with the new data
        $reseller->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Reseller Updated Successfully!',
            'data' => [
                'reseller' => new ResellerResource($reseller),
            ],
        ], 200);
    }

    /**
     * Add  bank Details for reseller.
     */
    public function addBankDetails(BankDetailRequest $request, int|string $reseller): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);
        $data = $request->all();
        $data['reseller_id'] = $reseller;
        // Check for existing bank details for the reseller
        $existingBankDetails = ResellerBankDetail::where('reseller_id', $reseller)->first();

        // If existing bank details are found, delete them
        if ($existingBankDetails) {
            $existingBankDetails->delete();
        }
        $reseller_bank_details = ResellerBankDetail::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Reseller Bank Details Added Successfully!',
            'data' => [
                'reseller_bank_details' => new ResellerBankDetailResource($reseller_bank_details),
            ],
        ], 201);
    }

    /**
     * Add  Payout History of reseller.
     */
    public function addPayouthistory(PayoutHistoryRequest $request, int|string $reseller): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);
        $data = $request->all();
        // Set the reseller_id in the data array
        $data['reseller_id'] = $reseller;

        // Fetch the account_number from bank_details table where reseller_id matches
        /** @var ?ResellerBankDetail $bankDetail */
        $bankDetail = DB::table('reseller_bank_details')
            ->where('reseller_id', $reseller)
            ->first();

        // Check if bank details are found
        if ($bankDetail) {
            $data['account_number'] = $bankDetail->account_number;
        } else {
            // Handle the case where no bank details are found for the reseller
            return response()->json(['error' => 'Bank details not found for the reseller.'], 404);
        }

        // Add the current date to the data array
        $data['date'] = Carbon::now()->format('Y-m-d\TH:i:s.u\Z');
        $payout_history = ResellerPayoutHistory::create($data);
        // Calculate the total amount and breakdown
        $latestPayoutDate = $payout_history->created_at;
        $resellerNumber = User::find($reseller)->reseller_number;
        $resellerLevel = User::find($reseller)->reseller_level;
        // Get the commission rate based on the reseller level and reseller number
        $commissionRate = 0;
        $resellerLevelConfig = ResellerLevelConfiguration::where('reseller_id', $reseller)->first();

        if ($resellerLevelConfig) {
            if ($resellerLevel == 'Basic') {
                $commissionRate = $resellerLevelConfig->basic_commission ?? 0;
            } elseif ($resellerLevel == 'Pro') {
                $commissionRate = $resellerLevelConfig->pro_commission ?? 0;
            }
        }
        $totalAmountQuery = Company::where('reseller_number', $resellerNumber)
            ->whereHas('subscriptions', function (Builder $query): void {
                $query->where('subscriptions.created_at', function ($subQuery): void {
                    $subQuery->selectRaw('MAX(subscriptions.created_at)')
                        ->from('subscriptions')
                        ->whereColumn('subscriptions.company_id', 'companies.id');
                })
                    ->where('subscriptions.is_trial', BOOLEAN_FALSE);
            })
            ->where('is_active', BOOLEAN_TRUE)
            ->join('company_user_balance_deductions', 'companies.id', '=', 'company_user_balance_deductions.company_id');

        if ($latestPayoutDate) {
            $totalAmountQuery->where('company_user_balance_deductions.created_at', '>', $latestPayoutDate);
        }

        $companies = $totalAmountQuery->get(['companies.id', 'companies.name', 'company_user_balance_deductions.amount']);

        // Store the breakdown
        foreach ($companies as $company) {
            $amount = $company->getAttribute('amount');
            $commissionAmount = ($amount * $commissionRate) / 100;
            PayoutDetail::create([
                'payout_id' => $payout_history->id,
                'company_id' => $company->id,
                'amount' => $commissionAmount,
                'reseller_id' => $reseller,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Reseller Payout History Added Successfully!',
            'data' => [
                'reseller_payout_history' => new ResellerPayoutHistoryResource($payout_history),
            ],
        ], 201);

    }

    /**
     * Add  Reseller Level Configuration.
     */
    public function addLevelConfiguration(ResellerLevelConfigurationRequest $request, int|string $reseller): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);
        $data = $request->all();
        $data['reseller_id'] = $reseller;
        // Check for existing level configuration for the reseller
        $existingDetails = ResellerLevelConfiguration::where('reseller_id', $reseller)->first();

        // If existing level configurations are found, delete them
        if ($existingDetails) {
            $existingDetails->delete();
        }
        $reseller_level_configuration = ResellerLevelConfiguration::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Reseller Level Configuration Added Successfully!',
            'data' => [
                'reseller_level_configuration' => new ResellerLevelConfigurationResource($reseller_level_configuration),
            ],
        ], 201);
    }

    /**
     * Upgrade Reseller Level To Pro .
     */
    public function upgrade(Request $request, User $reseller): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);

        // Check if the reseller has ResellerLevelConfiguration and BankDetails
        $resellerConfig = ResellerLevelConfiguration::where('reseller_id', $reseller->id)->first();
        $bankDetails = ResellerBankDetail::where('reseller_id', $reseller->id)->first();

        if (! $resellerConfig || ! $bankDetails) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot upgrade level due to missing Reseller Level Configuration or Bank Details.',
            ], 400);
        }

        // Update the reseller's level to Pro
        $reseller->reseller_level = 'Pro';
        $reseller->reseller_level_change_at = Carbon::now()->toDateString();
        $reseller->save();

        return response()->json([
            'success' => true,
            'message' => 'Reseller level upgraded to Pro successfully.',
            'data' => [
                'reseller' => new ResellerResource($reseller),
            ],
        ], 201);
    }

    /**
     * Degrade Reseller Level To Basic .
     */
    public function degrade(Request $request, User $reseller): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);
        // Check if the reseller has ResellerLevelConfiguration and BankDetails
        $resellerConfig = ResellerLevelConfiguration::where('reseller_id', $reseller->id)->first();
        $bankDetails = ResellerBankDetail::where('reseller_id', $reseller->id)->first();

        if (! $resellerConfig || ! $bankDetails) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot degrade level due to missing Reseller Level Configuration or Bank Details.',
            ], 400);
        }

        // Update the reseller's level to Pro
        $reseller->reseller_level = 'Basic';
        $reseller->reseller_level_change_at = Carbon::now()->toDateString();
        $reseller->save();

        return response()->json([
            'success' => true,
            'message' => 'Reseller level degrade to Basic successfully.',
            'data' => [
                'reseller' => new ResellerResource($reseller),
            ],
        ], 201);
    }

    public function getPayoutDetails(int|string $reseller_id, int|string $payout_id): JsonResponse
    {
        // Fetch the payout history for the given payout_id and reseller_id
        $payout = ResellerPayoutHistory::where('reseller_id', $reseller_id)
            ->where('id', $payout_id)
            ->first();

        // Check if the payout exists
        if (! $payout) {
            return response()->json(['error' => 'Payout not found.'], 404);
        }

        // Fetch the previous payout date to find the correct range of company deductions
        $previousPayout = ResellerPayoutHistory::where('reseller_id', $reseller_id)
            ->where('id', '<', $payout_id)
            ->orderBy('id', 'desc')
            ->first();
            $reseller = User::find($reseller_id);
        // Determine the start date for the range
        $startDate = $previousPayout ? $previousPayout->created_at : '1970-01-01 00:00:00';
        // Fetch the companies that contributed to this payout within the date range
        $companies = Company::join('company_user_balance_deductions', 'companies.id', '=', 'company_user_balance_deductions.company_id')
            ->where('company_user_balance_deductions.created_at', '>', $startDate)
            ->where('company_user_balance_deductions.created_at', '<=', $payout->created_at)
            // ->where('companies.id', $reseller->company_id)
            ->select('companies.id', 'companies.name', 'company_user_balance_deductions.amount')
            ->get()
            ->map(function ($company) use ($reseller_id): array {
                // Fetch the reseller configuration
                $resellerConfig = ResellerLevelConfiguration::where('reseller_id', $reseller_id)->first();

                // Determine the commission rate based on the reseller's level
                $commissionRate = 0;
                $reseller = User::find($reseller_id);
                if ($reseller->reseller_level == 'Basic') {
                    $commissionRate = $resellerConfig->basic_commission ?? 0;
                } elseif ($reseller->reseller_level == 'Pro') {
                    $commissionRate = $resellerConfig->pro_commission ?? 0;
                }

                // Calculate the commission amount
                $amount = $company->getAttribute('amount');
                $commissionAmount = ($amount * $commissionRate) / 100;

                return [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'amount' => $amount,
                    'commission_percentage' => $commissionRate,
                    'commission_amount' => $commissionAmount,
                ];
            });

        // Return the payout details with the list of companies and their contributions
        return response()->json([
            'success' => true,
            'data' => [
                'reference_number' => $payout->reference_number,
                'account_number' => $payout->account_number,
                'total_amount' => (float) $payout->amount,
                'companies' => $companies
            ],
        ]);
    }

    /**
     * Get reseller companies.
     */
    public function resellerCompanies(Request $request): JsonResponse
    {
        try {
            $this->authorize('resellerDashboard', User::class);

            $user = Auth::user();
            $resellerNumber = $user->reseller_number;

            // Define filters
            $company_name = $request->input('company_name');
            $status = $request->input('status');
            $cr_number = $request->input('CR_number');
            $phone = $request->input('phone');
            $monthly_new = $request->boolean('monthly_new');
            $monthly_inactive = $request->boolean('monthly_inactive');
            $failed_odoo_accounts = $request->boolean('failed_odoo_accounts');
            $idle = $request->boolean('idle');
            $is_trial = $request->input('is_trial');

            $companies = Company::where('reseller_number', $resellerNumber)
                ->when($company_name, function (Builder $query, ?string $company_name): void {
                    $query->where('name', 'like', '%'.$company_name.'%');
                })
                ->when($status, function (Builder $query, ?int $status): void {
                    $query->where('status', $status);
                })
                ->when($cr_number, function (Builder $query, ?string $cr_number): void {
                    $query->where('cr', $cr_number);
                })
                ->when($phone, function (Builder $query, ?string $phone): void {
                    $query->whereHas('users', function (Builder $q) use ($phone): void {
                        $q->where('phone', 'like', '%'.$phone.'%');
                    });
                })
                ->when($monthly_new, function (Builder $query, ?bool $monthly_new): void {
                    $query->whereMonth('created_at', '=', Carbon::now()->month);
                })
                ->when($monthly_inactive, function (Builder $query, ?bool $monthly_inactive): void {
                    $query->whereMonth('updated_at', '=', Carbon::now()->month)
                        ->whereIn('status', [
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
                ->when($is_trial, function (Builder $query) use ($is_trial): void {
                    $query->whereHas('subscriptions', function (Builder $q) use ($is_trial): void {
                        $q->where('is_trial', $is_trial);
                    });
                })
                ->latest()
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
        $this->authorize('resellerDashboard', User::class);

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

    /**
     * get reseller Dashboard Data.
     */
    public function dashboard(): JsonResponse
    {
        try {
            // Ensure the user is authorized to access the reseller dashboard
            $this->authorize('resellerDashboard', User::class);

            // Retrieve the currently authenticated user
            $user = Auth::user();

            // Ensure the user is a reseller and has a reseller number
            if (! $user->reseller_number) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reseller number not found for the user.',
                ], 404);
            }

            $resellerNumber = $user->reseller_number;

            // Fetch the count of paid customers
            $paidCustomersCount = Company::where('reseller_number', $resellerNumber)
                ->whereHas('subscriptions', function (Builder $query): void {
                    $query->where('subscriptions.created_at', function ($subQuery): void {
                        $subQuery->selectRaw('MAX(subscriptions.created_at)')
                            ->from('subscriptions')
                            ->whereColumn('subscriptions.company_id', 'companies.id');
                    })
                        ->where('subscriptions.is_trial', BOOLEAN_FALSE);
                })
                ->where('is_active', true)
                ->count();

            // Fetch the count of trial customers
            $trialCustomersCount = Company::where('reseller_number', $resellerNumber)
                ->whereHas('subscriptions', function (Builder $query): void {
                    $query->where('subscriptions.created_at', function ($subQuery): void {
                        $subQuery->selectRaw('MAX(subscriptions.created_at)')
                            ->from('subscriptions')
                            ->whereColumn('subscriptions.company_id', 'companies.id');
                    })
                        ->where('subscriptions.is_trial', BOOLEAN_TRUE);
                })
                ->count();

            // Retrieve the reseller level
            $resellerLevel = $user->reseller_level;
            // KYC Companies
            $submitting_kyc_count = Company::where('reseller_number', $resellerNumber)->where('status', COMPANY_STATUS_KYC)->count();
            // Inactive Companies
            $inactive_customers_count = Company::where('reseller_number', $resellerNumber)->where('status', COMPANY_STATUS_BLOCKED)->count();
            // Active Companies
            $active_customers_count = Company::where('reseller_number', $resellerNumber)->where('status', COMPANY_STATUS_ACTIVE)->count();
            // Under Review Companies
            $under_review_customers_count = Company::where('reseller_number', $resellerNumber)->where('status', COMPANY_STATUS_REVIEW)->count();
            $monthly_new_customers_count = Company::where('reseller_number', $resellerNumber)->whereMonth('created_at', '=', Carbon::now()->month)->count();
            $monthly_inactive_customers_count = Company::where('reseller_number', $resellerNumber)->whereMonth('updated_at', '=', Carbon::now()->month)->whereIn('status', [
                COMPANY_STATUS_BLOCKED,
                COMPANY_STATUS_SUBSCRIPTION_ENDED,
            ])->count();
            $idle_customers_count = Company::where('reseller_number', $resellerNumber)->where('last_active_at', null)->orWhere('last_active_at', '<=', Carbon::now()->subDays(IDLE_CUSTOMER_DAYS))->count();
            $new_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CREATED)->count();
            $in_progress_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_IN_PROGRESS)->count();
            $done_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_DONE)->count();
            $closed_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CLOSED)->count();
            $late_tickets_count = $this->helpdeskQuery()
                ->where('status', HELPDESK_TICKET_CREATED)
                ->where('created_at', '<=', Carbon::now()->subHours(24))
                ->count();
            $delayed_tickets_count = $this->helpdeskQuery()
                ->where('status', HELPDESK_TICKET_IN_PROGRESS)
                ->where('status_updated_at', '<=', Carbon::now()->subHours(48))
                ->count();

            // Return the data in a JSON response
            return response()->json([
                'success' => true,
                'data' => [
                    'active_customers_count' => $active_customers_count,
                    'inactive_customers_count' => $inactive_customers_count,
                    'paid_customers_count' => $paidCustomersCount,
                    'trial_customers_count' => $trialCustomersCount,
                    'submitting_kyc_count' => $submitting_kyc_count,
                    'monthly_new_customers_count' => $monthly_new_customers_count,
                    'monthly_inactive_customers_count' => $monthly_inactive_customers_count,
                    'idle_customers_count' => $idle_customers_count,
                    'new_tickets_count' => $new_tickets_count,
                    'in_progress_tickets_count' => $in_progress_tickets_count,
                    'done_tickets_count' => $done_tickets_count,
                    'closed_tickets_count' => $closed_tickets_count,
                    'late_tickets_count' => $late_tickets_count,
                    'delayed_tickets_count' => $delayed_tickets_count,
                    'reseller_level' => $resellerLevel,
                ],
            ], 200);

        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a listing of the helpdesk tickets.
     */
    public function helpdeskShow(Request $request): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);

        $new_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CREATED)->count();
        $in_progress_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_IN_PROGRESS)->count();
        $done_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_DONE)->count();
        $closed_tickets_count = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CLOSED)->count();

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Tickets Counts',
            'data' => [
                'new_tickets_count' => $new_tickets_count,
                'in_progress_tickets_count' => $in_progress_tickets_count,
                'done_tickets_count' => $done_tickets_count,
                'closed_tickets_count' => $closed_tickets_count,
            ],
        ]);
    }

    /**
     * Display a listing of the new helpdesk tickets.
     */
    public function newTickets(Request $request): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);

        $new_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CREATED)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk New Tickets',
            'data' => new HelpdeskTicketCollection($new_tickets),
        ]);
    }

    /**
     * Display a listing of the in-progress helpdesk tickets.
     */
    public function inProgressTickets(Request $request): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);

        $in_progress_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_IN_PROGRESS)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk In Progress Tickets',
            'data' => new HelpdeskTicketCollection($in_progress_tickets),
        ]);
    }

    /**
     * Display a listing of the done helpdesk tickets.
     */
    public function doneTickets(Request $request): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);

        $done_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_DONE)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Done Tickets',
            'data' => new HelpdeskTicketCollection($done_tickets),
        ]);
    }

    /**
     * Display a listing of the closed helpdesk tickets.
     */
    public function closedTickets(Request $request): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);

        $closed_tickets = $this->helpdeskQuery()->where('status', HELPDESK_TICKET_CLOSED)->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Closed Tickets',
            'data' => new HelpdeskTicketCollection($closed_tickets),
        ]);
    }

    /**
     * Display a listing of the late helpdesk tickets.
     */
    public function lateTickets(Request $request): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);

        $closed_tickets = $this->helpdeskQuery()
            ->where('status', HELPDESK_TICKET_CREATED)
            ->where('created_at', '<=', Carbon::now()->subHours(24))
            ->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Late Tickets',
            'data' => new HelpdeskTicketCollection($closed_tickets),
        ]);
    }

    /**
     * Display a listing of the delayed helpdesk tickets.
     */
    public function delayedTickets(Request $request): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);

        $closed_tickets = $this->helpdeskQuery()
            ->where('status', HELPDESK_TICKET_IN_PROGRESS)
            ->where('status_updated_at', '<=', Carbon::now()->subHours(48))
            ->paginate(PER_PAGE_RECORDS);

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Delayed Tickets',
            'data' => new HelpdeskTicketCollection($closed_tickets),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateTicket(UpdateHelpdeskTicketRequest $request, HelpdeskTicket $helpdesk_ticket): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);

        $helpdesk_ticket->update($request->safe()->only(['status', 'issue_type_id', 'issue_comment']));
        if ($helpdesk_ticket->wasChanged('status')) {
            $helpdesk_ticket->status_updated_at = Carbon::now()->toDateTimeString();
            $helpdesk_ticket->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Ticket status updated Successfully!',
            'data' => [
                'helpdesk_ticket' => new HelpdeskTicketResource($helpdesk_ticket),
            ],
        ], 200);
    }

    /**
     * Forward Ticket to the support team.
     */
    public function forwardTicket(Request $request, HelpdeskTicket $helpdesk_ticket): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);
        $support_agent_id = $helpdesk_ticket->assigned_to;
        if ($support_agent_id) {
            $helpdesk_ticket->manage_by = $support_agent_id;
        } elseif ($last_ticket = HelpdeskTicket::latest()->first()) {
            // Find the next support agent whom this ticket is to be assigned
            // Find next support agent
            $next_agent = User::where('type', USER_TYPE_ADMIN_STAFF)
                ->where('is_support_agent', BOOLEAN_TRUE)
                ->where('id', '>', $last_ticket->assigned_to ?? 0)
                ->orderBy('id', 'asc')
                ->first();
            if ($next_agent) {
                $helpdesk_ticket->manage_by = $next_agent->id;
            } else {
                $support_agent = User::where('type', USER_TYPE_ADMIN_STAFF)
                    ->where('is_support_agent', BOOLEAN_TRUE)
                    ->first();

                $helpdesk_ticket->manage_by = $support_agent ? $support_agent->id : null;
            }
        } else {
            $support_agent = User::where('type', USER_TYPE_ADMIN_STAFF)
                ->where('is_support_agent', BOOLEAN_TRUE)
                ->first();
            $helpdesk_ticket->manage_by = $support_agent ? $support_agent->id : null;
        }

        // Save the updated helpdesk ticket
        $helpdesk_ticket->save();

        // Return a successful response
        return response()->json([
            'success' => true,
            'message' => 'Helpdesk Ticket forwarded to support agent successfully!',
            'data' => [
                'helpdesk_ticket' => new HelpdeskTicketResource($helpdesk_ticket),
            ],
        ], 200);
    }

    /**
     * Change status of a reseller.
     */
    public function changeStatus(ChangeStatusRequest $request, User $reseller): JsonResponse
    {
        $this->authorize('adminDashboard', User::class);
        $newStatus = $request->input('status');
        $message = '';

        if ($reseller->status == RESELLER_STATUS_ACTIVE) {
            if ($newStatus != RESELLER_STATUS_BLOCKED) {
                return response()->json([
                    'success' => false,
                    'message' => 'Active resellers can only be set to blocked.',
                ], 422);
            }
        } elseif ($newStatus == RESELLER_STATUS_ACTIVE) {
            $hasBankDetails = ResellerBankDetail::where('reseller_id', $reseller->id)->exists();
            if (! $hasBankDetails) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reseller must have bank details to be activated.',
                ], 422);
            }
            $hasLevelConfigurations = ResellerLevelConfiguration::where('reseller_id', $reseller->id)->exists();
            if (! $hasLevelConfigurations) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reseller must have level configurations to be activated.',
                ], 422);
            }
        }

        if ($newStatus == RESELLER_STATUS_REJECTED) {
            $reseller->rejection_reason = $request->input('rejection_reason');
        }

        $reseller->status = $newStatus;
        $reseller->save();

        switch ($newStatus) {
            case RESELLER_STATUS_ACTIVE:
                $message = 'Reseller activated successfully.';
                break;
            case RESELLER_STATUS_BLOCKED:
                $message = 'Reseller blocked successfully.';
                break;
            case RESELLER_STATUS_REJECTED:
                $message = 'Reseller rejected successfully.';
                break;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'reseller' => new ResellerResource($reseller),
            ],
        ], 201);
    }

    /**
     * Update profile of login reseller
     */
    public function updateReseller(UpdateResellerProfileRequest $request): JsonResponse
    {
        $this->authorize('resellerDashboard', User::class);

        /** @var \App\Models\User? $reseller */
        $reseller = auth()->user();

        if (! auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }
        $data = $request->safe()->except(['password', 'is_active']);
        if ($reseller->status == RESELLER_STATUS_REJECTED) {
            $data['status'] = RESELLER_STATUS_INREVIEW;
        }
        if ($request->has('password') && ! empty($request->password)) {
            $data['password'] = bcrypt($request->password);
        }

        if ($request->has('status')) {
            $data['status'] = boolval($request->status);
        }

        if ($request->has('user_type') && $request->user_type === 'individual') {
            $data['company_registration_document'] = null;
            $data['reseller_company_name'] = null;
        }

        // Handling the User photo update
        if ($request->file('user_photo_id')) {
            // Delete the old photo if it exists
            if ($reseller->user_photo_id && \Storage::exists($reseller->user_photo_id)) {
                \Storage::delete($reseller->user_photo_id);
            }

            // Get new File
            /** @var UploadedFile $image */
            $image = $request->file('user_photo_id');
            // Generate Random Name
            $file_name = Str::random(14).'_'.time().'.'.$image->extension();
            // Set File Path
            $access_path = 'public/user_images/'.$reseller->id;
            $file_path = storage_path('app/'.$access_path);
            // Create directory if not exists
            if (! is_dir($file_path)) {
                mkdir($file_path, 0775, true);
            }
            // Create Image Object
            $img = Image::make($image->path());
            // Resize, Crop and Save
            $img->fit(500)->save($file_path.'/'.$file_name);
            // Save Path to Product
            $user_photo_id = $access_path.'/'.$file_name;
            $data['user_photo_id'] = $user_photo_id;
        }

        // Handling the company registration document update
        if ($request->file('company_registration_document')) {
            // Delete the old document if it exists
            if ($reseller->company_registration_document && \Storage::exists($reseller->company_registration_document)) {
                \Storage::delete($reseller->company_registration_document);
            }

            // Get the new file
            /** @var UploadedFile $document */
            $document = $request->file('company_registration_document');

            // Generate a random file name
            $file_name = Str::random(14).'_'.time().'.'.$document->extension();

            // Set the file path
            $access_path = 'public/reseller_company_registration/'.$reseller->id;
            $file_path = storage_path('app/'.$access_path);

            // Create the directory if it doesn't exist
            if (! is_dir($file_path)) {
                mkdir($file_path, 0775, true);
            }

            // Move the document to the designated path
            $document->move($file_path, $file_name);

            // Save the path to the company_registration_document
            $company_registration_document_path = $access_path.'/'.$file_name;

            // Add the company_registration_document path to the data array
            $data['company_registration_document'] = $company_registration_document_path;
        }

        // Update the reseller with the new data
        $reseller->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Reseller Updated Successfully!',
            'data' => [
                'reseller' => new ResellerResource($reseller),
            ],
        ], 200);
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

    /** @return Builder<HelpdeskTicket> */
    private function helpdeskQuery(): Builder
    {
        $query = HelpdeskTicket::query();
        if (user_is_reseller()) {
            $query = $query->where('reseller_agent', auth()->id());
        }

        return $query;
    }
}
