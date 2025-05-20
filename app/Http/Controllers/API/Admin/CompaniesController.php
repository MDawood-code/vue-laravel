<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\UploadedFile;
use App\Events\CompanyActivated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Company\DeleteUploadedFileRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Http\Traits\SMSTrait;
use App\Models\BusinessTypeVerification;
use App\Models\Company;
use App\Models\CrmLog;
use App\Models\User;
use App\Notifications\CompanyAdminStaffChanged;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\Validation\Rules\File;
use Image;

/**
 * @group Admin
 *
 * @subgroup Company
 *
 * @subgroupDescription APIs for managing Company
 */
class CompaniesController extends Controller
{
    use SMSTrait;

    /**
     * Display all companies.
     *
     * @queryParam page int Page number to show. Defaults to 1.
     * @queryParam company_name string Search by given company name. Optional.
     * @queryParam status integer Filter by given status. Optional.
     * @queryParam CR_number string Search by given cr number. Optional.
     * @queryParam phone string Search by given phon enumber. Optional.
     * @queryParam monthly_new boolean Filter by monthly_new bool value. Optional. Example: 1
     * @queryParam monthly_inactive boolean Filter by monthly_inactive bool value. Optional. Example: 1
     * @queryParam monthly_failed_odoo_accounts boolean Filter by monthly_failed_odoo_accounts bool value. Optional. Example: 1
     * @queryParam idle boolean Filter by idle bool value. Optional. Example: 1
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Company::class);

        $company_name = is_null($request->company_name) ? null : $request->string('company_name');
        $referral_code = is_null($request->referral_code) ? null : $request->string('referral_code');
        $reseller_number = is_null($request->reseller_number) ? null : $request->string('reseller_number');
        $status = is_null($request->status) ? null : $request->integer('status');
        $cr_number = is_null($request->CR_number) ? null : $request->string('CR_number');
        $phone = is_null($request->phone) ? null : $request->string('phone');
        $monthly_new = is_null($request->monthly_new) ? null : $request->boolean('monthly_new');
        $monthly_inactive = is_null($request->monthly_inactive) ? null : $request->boolean('monthly_inactive');
        $failed_odoo_accounts = is_null($request->failed_odoo_accounts) ? null : $request->boolean('failed_odoo_accounts');
        $idle = is_null($request->idle) ? null : $request->boolean('idle');
        $is_trial = is_null($request->is_trial) ? null : $request->string('is_trial');

        $companies = $this->companiesQuery()
            ->when($company_name, function (Builder $query, ?Stringable $company_name): void {
                $query->where('name', 'like', '%'.$company_name.'%');
            })
            ->when($referral_code, function (Builder $query, ?Stringable $referral_code): void {
                $query->where('referral_code', 'like', '%'.$referral_code.'%');
            })
            ->when($reseller_number, function (Builder $query, ?Stringable $reseller_number) use ($is_trial): void {
                $query->where('reseller_number', $reseller_number)
                    ->whereHas('subscriptions', function (Builder $q) use ($is_trial): void {
                        $q->where('is_trial', $is_trial);
                    });
            })
            ->when($status, function (Builder $query, ?int $status): void {
                if ($status !== INVOICE_RECHARGE_REQUEST) {
                    $query->where('status', $status);
                } else {
                    $query->whereHas('invoices', function ($query): void {
                        $query->where('status', INVOICE_STATUS_UNPAID)->whereNotNull('stcpay_reference_id');
                    });
                }
            })
            ->when($status === INVOICE_STATUS_UNPAID, function (Builder $query, ?int $status): void {
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
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @queryParam page int Page number to show. Defaults to 1.
     * @queryParam company_name string Search by given company name. Optional.
     * @queryParam status integer Filter by given status. Optional.
     * @queryParam CR_number string Search by given cr number. Optional.
     * @queryParam phone string Search by given phon enumber. Optional.
     * @queryParam monthly_new boolean Filter by monthly_new bool value. Optional. Example: 1
     * @queryParam monthly_inactive boolean Filter by monthly_inactive bool value. Optional. Example: 1
     * @queryParam monthly_failed_odoo_accounts boolean Filter by monthly_failed_odoo_accounts bool value. Optional. Example: 1
     * @queryParam idle boolean Filter by idle bool value. Optional. Example: 1
     */
    public function show(Request $request, Company $company): JsonResponse
    {
        $this->authorize('adminView', $company);

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
                if ($status !== INVOICE_RECHARGE_REQUEST) {
                    $query->where('status', $status);
                } else {
                    $query->whereHas('invoices', function ($query): void {
                        $query->where('status', INVOICE_STATUS_UNPAID)->whereNotNull('stcpay_reference_id');
                    });
                }
            })
            ->when($status === INVOICE_STATUS_UNPAID, function (Builder $query, ?int $status): void {
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
                if ($status !== INVOICE_RECHARGE_REQUEST) {
                    $query->where('status', $status);
                } else {
                    $query->whereHas('invoices', function ($query): void {
                        $query->where('status', INVOICE_STATUS_UNPAID)->whereNotNull('stcpay_reference_id');
                    });
                }
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

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, Company $company): JsonResponse
    // {
    //     $company->addons = $request->addons;
    //     $company->save();

    //     // Revoke Company's All Users Tokens
    //     foreach ($company->employees as $employee) {
    //         /** @var \Laravel\Passport\Token $token * */
    //         foreach ($employee->tokens as $token) {
    //             $token->revoke();
    //         }
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Company has been updated successfully.',
    //         'data' => [
    //             'company' => new CompanyResource($company),
    //         ],
    //     ], 201);
    // }

    /**
     * Delete the uploaded file.
     */
    public function deleteUploadedFile(DeleteUploadedFileRequest $request, Company $company): JsonResponse
    {
        $this->authorize('deleteUploadedFile', $company);

        $fileType = $request->file_type;
        if ($company->{$fileType} && Storage::exists(str_replace('/storage', 'public', $company->{$fileType}))) {
            Storage::delete(str_replace('/storage', 'public', $company->{$fileType}));
            $company->{$fileType} = null;
            $company->save();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
                'data' => [],
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => 'File not found',
            'data' => [],
        ], 400);
    }

    /**
     * Activate the specified company.
     */
    public function activate(Request $request, Company $company): JsonResponse
    {
        $this->authorize('activate', $company);

        // Questionnaire is mandatory to be filled before activating the company
        if ($company->questionnaire()->doesntExist()) {
            return response()->json([
                'success' => false,
                'message' => 'Fill the questionnaire first.',
                'data' => [],
            ], 200);
        }

        if ($company->status === COMPANY_STATUS_REVIEW || $company->status === COMPANY_STATUS_BLOCKED) {
            if ($company->isInvoiceUnpaidForDays(7)) {
                if (! user_is_super_admin()) {
                    $company->status = COMPANY_STATUS_SUBSCRIPTION_INVOICE_GENERATED;

                    return response()->json([
                        'success' => false,
                        'message' => 'Customer has unpaid invoices.',
                        'data' => [],
                    ], 200);
                }

                // for super admin, we are setting company status to active.
                // This change Requested by Tahir bhai
                $company->status = COMPANY_STATUS_ACTIVE;
                $company->is_active = true;
                $company->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Company has been activated successfully.',
                    'data' => [
                        'company' => new CompanyResource($company),
                        'sms_response' => null,
                    ],
                ], 201);
            } else {
                $company->status = COMPANY_STATUS_ACTIVE;
            }
        }
        $company->is_active = true;
        $company->save();

        $subscription = $company->subscriptions()->latest()->first();
        // Update Subscription Start / End Date
        // Activating Company First Time
        if (empty($subscription->start_date) && empty($subscription->end_date)) {
            $start_date = Carbon::now();
            $end_date = Carbon::now()->addDays($subscription->validity_days);
            // If Time is Past 6 am increase 1 day
            if ($start_date->setTimezone('Asia/Riyadh')->format('H') > 6) {
                $end_date = $end_date->addDays(1);
            }
            $subscription->start_date = $start_date->toDateString();
            $subscription->end_date = $end_date->toDateString();
            $subscription->save();

            // Update Users License Start/End Date
            foreach ($subscription->userLicenses()->latest()->get() as $license) {
                if (empty($license->start_date) && empty($license->end_date)) {
                    $license->start_date = $start_date;
                    $license->end_date = $end_date;
                    $license->save();
                }
            }
        }

        $response = null;
        if (App::environment('production')) {
            $manager = $company->employees()->where('type', USER_TYPE_BUSINESS_OWNER)->first();
            $msg_text = urlencode('Your account has been activated, Kindly login to AnyPOS Portal.');
            // Send SMS when Profile is activated
            $response = $this->sendSMS($msg_text, $manager->phone);
        }

        CompanyActivated::dispatch($company);
        $company->crmLogs()->create([
            'created_by' => auth()->id(),
            'action' => 'activated the company',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company has been activated successfully.',
            'data' => [
                'company' => new CompanyResource($company),
                'sms_response' => $response,
            ],
        ], 201);
    }

    /**
     * Deactivate the specified company.
     */
    public function deactivate(Request $request, Company $company): JsonResponse
    {
        $this->authorize('deactivate', $company);

        $company->status = COMPANY_STATUS_BLOCKED;
        $company->is_active = false;
        $company->save();

        $company->crmLogs()->create([
            'created_by' => auth()->id(),
            'action' => 'deactivated the company',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Company has been deactivated successfully.',
            'data' => [
                'company' => new CompanyResource($company),
            ],
        ], 201);
    }

    /**
     * Update the specified company.
     *
     * @bodyParam business_name string optional The new business name of the company. Example: Acme Corporation
     * @bodyParam business_type string optional The new business type of the company. Example: Retail
     * @bodyParam business_type_verification int optional The ID of the business type verification document. Example: 1
     * @bodyParam cr string optional The new commercial registration number of the company. Example: 1234567890
     * @bodyParam vat string optional The new VAT number of the company. Example: 123456789012345
     * @bodyParam address string optional The new address of the company. Example: 123 Main St, Anytown
     * @bodyParam round_off boolean optional Whether to round off the billing amounts. Example: true
     * @bodyParam billing_address string optional The new billing address of the company. Example: 456 Elm St, Anytown
     * @bodyParam billing_city_id int optional The ID of the new billing city. Example: 2
     * @bodyParam billing_state_id int optional The ID of the new billing state. Example: 3
     * @bodyParam billing_country string optional The new billing country of the company. Example: Anyland
     * @bodyParam billing_post_code string optional The new billing postal code of the company. Example: 12345
     * @bodyParam is_billing_add_same_as_postal boolean optional Whether the billing address is the same as the postal address. Example: false
     * @bodyParam app_config json optional The new application configuration in JSON format. Example: {"theme":"dark"}
     * @bodyParam device_token string optional The new device token for push notifications. Example: abcdef123456
     * @bodyParam preferred_contact_time string optional The new preferred contact time. Example: Afternoon
     * @bodyParam is_vat_exempt boolean optional Whether the company is exempt from VAT. Example: false
     * @bodyParam logo file optional The new logo file for the company. Must be an image.
     * @bodyParam cr_certificate file optional The new commercial registration certificate file. Can be an image or document.
     * @bodyParam cr_verification file optional The new commercial registration verification document. Can be an image or document.
     * @bodyParam vat_certificate file optional The new VAT certificate file. Can be an image or document.
     * @bodyParam vat_verification file optional The new VAT verification document. Can be an image or document.
     */
    public function updateDetails(Request $request, Company $company): JsonResponse
    {
        $this->authorize('updateByAdmin', $company);
        if ($company->is_onboarding_complete == false) {

            $company->is_onboarding_complete = true;
            $company->save();
        }

        // TODO: Implement More Validations on Some Fields
        // Edit only if User is admin or Want's to manage his/her own profile
        $user = $company->users()->where('type', USER_TYPE_BUSINESS_OWNER)->first();
        if (
            count($request->all()) === 0 ||
            ( /*&& empty($request->phone)*/
                empty($request->business_name)
                && empty($request->business_type)
                && empty($request->business_type_verification)
                && empty($request->cr)
                && empty($request->vat)
                && empty($request->address)
                && ($request->has('round_off') && $request->round_off !== null)
                && empty($request->billing_address)
                && empty($request->billing_city_id)
                && empty($request->billing_state_id)
                && empty($request->billing_country)
                && empty($request->billing_post_code)
                && empty($request->is_billing_add_same_as_postal)
                && empty($request->app_config)
                && empty($request->device_token)
                && empty($request->preferred_contact_time)
                && empty($request->is_vat_exempt)
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No Valid data to process, add some data.',
                'data' => [],
            ], 400);
        }

        $status = true;
        $fields = [];

        // Validate and update Phone Number
        //        if ($request->phone) {
        //            $validator = Validator::make($request->all(), [
        //                'phone' => 'required|string|min:10'
        //            ]);
        //            if ($validator->fails()) {
        //                $status = false;
        //                $fields['phone'] = [
        //                    'success' => false,
        //                    'message' => $validator->messages()->toArray()
        //                ];
        //            } else {
        //                $user->phone = $request->phone;
        //                $fields['phone'] = [
        //                    'success' => true,
        //                    'message' => 'Phone has been updated.'
        //                ];
        //            }
        //        }

        // Validate and update Logo
        if ($request->file('logo')) {
            $validator = Validator::make($request->all(), [
                'logo' => [
                    'nullable',
                    File::image()
                        ->max(LOGO_SIZE * 1024),
                ],
            ]);
            if ($validator->fails()) {
                $status = false;
                $fields['logo'] = [
                    'success' => false,
                    'message' => $validator->messages()->toArray(),
                ];
            } elseif ($request->file('logo') != null) {
                // Get File
                /** @var UploadedFile $logo_image */
                $logo_image = $request->file('logo');

                // Generate Random Name
                $file_name = Str::random(14).'_'.time().'.'.$logo_image->extension();

                // Set File Path
                $access_path = 'public/business_logos';
                $file_path = storage_path('app/'.$access_path);

                // Create Image Object
                $img = Image::make($logo_image->path());

                // Resize constraint aspectRatio, upSize and Save
                $img->resize(200, 200, function ($constraint): void {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->save($file_path.'/'.$file_name);
                // Save Path to Logo
                $company->logo = Storage::url($access_path.'/'.$file_name);
                //                $user->logo = $request->file('logo')->store('public/shop_logos');
                $fields['logo'] = [
                    'success' => true,
                    'message' => 'Shop Logo has been updated.',
                ];
            }
        }

        // Validate and update Shop Name
        if ($request->business_name) {
            $validator = Validator::make($request->all(), [
                'business_name' => 'required|string|min:3',
            ]);
            if ($validator->fails()) {
                $status = false;
                $fields['business_name'] = [
                    'success' => false,
                    'message' => $validator->messages()->toArray(),
                ];
            } else {
                $company->name = $request->business_name;
                $fields['business_name'] = [
                    'success' => true,
                    'message' => 'Shop Name has been updated.',
                ];
            }
        }

        // Validate and update Business Type
        if ($request->business_type) {
            $company->business_type = $request->business_type;
            $fields['business_type'] = [
                'success' => true,
                'message' => 'Business Type has been updated.',
            ];
        }

        // Validate and update Business Type Verification
        if ($request->business_type_verification) {
            if (BusinessTypeVerification::where('id', $request->business_type_verification)->exists()) {
                $company->business_type_verification_id = $request->business_type_verification;
                $fields['business_type_verification'] = [
                    'success' => true,
                    'message' => 'Business Type Verification has been updated.',
                ];
            } else {
                $status = false;
                $fields['business_type_verification'] = [
                    'success' => false,
                    'message' => 'Invalid Business Type Verification.',
                ];
            }
        }

        // Validate and update CR No.
        if ($request->cr) {
            $validator = Validator::make($request->all(), [
                'cr' => 'string|min:10|max:10',
            ]);
            if ($validator->fails()) {
                $status = false;
                $fields['cr'] = [
                    'success' => false,
                    'message' => $validator->messages()->toArray(),
                ];
            } else {
                $company->cr = $request->cr;
                $fields['cr'] = [
                    'success' => true,
                    'message' => 'CR No. has been updated.',
                ];
            }
        }

        // Validate and update CR Certificate
        if ($request->file('cr_certificate')) {
            $validator = Validator::make($request->all(), [
                'cr_certificate' => [
                    'nullable',
                    File::types([
                        'jpg',
                        'jpeg',
                        'png',
                        'pdf',
                        'doc',
                        'docx',
                    ])
                        ->max(CR_VAT_FILE_SIZE * 1024),
                ],
            ]);
            if ($validator->fails()) {
                $status = false;
                $fields['cr_certificate'] = [
                    'success' => false,
                    'message' => $validator->messages()->toArray(),
                ];
            } elseif ($request->file('cr_certificate') != null) {
                // Get File
                /** @var UploadedFile $cr_certificate */
                $cr_certificate = $request->file('cr_certificate');
                // Generate Random Name
                $file_name = Str::random(14).'_'.time().'.'.$cr_certificate->extension();
                // Set File Path
                $file_path = 'public/cr_certificates';
                // Save CR Certificate
                $cr_certificate->storeAs($file_path, $file_name);
                // Save Path to CR Certificate
                $company->cr_certificate = Storage::url($file_path.'/'.$file_name);
                $fields['cr_certificate'] = [
                    'success' => true,
                    'message' => 'Shop CR Certificate has been updated.',
                ];
            }
        }

        // Validate and upload CR Certificate Verification
        if ($request->file('cr_verification')) {
            $validator = Validator::make($request->all(), [
                'cr_verification' => [
                    'nullable',
                    File::types([
                        'jpg',
                        'jpeg',
                        'png',
                        'pdf',
                        'doc',
                        'docx',
                    ])
                        ->max(CR_VAT_FILE_SIZE * 1024),
                ],
            ]);
            if ($validator->fails()) {
                $status = false;
                $fields['cr_verification'] = [
                    'success' => false,
                    'message' => $validator->messages()->toArray(),
                ];
            } elseif ($request->file('cr_verification') != null) {
                // Get File
                /** @var UploadedFile $cr_verification */
                $cr_verification = $request->file('cr_verification');
                // Generate Random Name
                $file_name = Str::random(14).'_'.time().'.'.$cr_verification->extension();
                // Set File Path
                $file_path = 'public/cr_verifications';
                // Save CR Certificate Verification
                $cr_verification->storeAs($file_path, $file_name);
                // Save Path to CR Certificate Verification
                $company->cr_verification = Storage::url($file_path.'/'.$file_name);
                $fields['cr_verification'] = [
                    'success' => true,
                    'message' => 'Shop CR Certificate Verification has been updated.',
                ];
            }
        }

        // Validate and update VAT No.
        if ($request->vat) {
            $validator = Validator::make($request->all(), [
                'vat' => 'string|min:15|max:15',
            ]);
            if ($validator->fails()) {
                $status = false;
                $fields['vat'] = [
                    'success' => false,
                    'message' => $validator->messages()->toArray(),
                ];
            } else {
                $company->vat = $request->vat;
                $fields['vat'] = [
                    'success' => true,
                    'message' => 'VAT No. has been updated.',
                ];
            }
        }

        // Validate and update VAT Certificate
        if ($request->file('vat_certificate')) {
            $validator = Validator::make($request->all(), [
                'vat_certificate' => 'file|nullable',
            ]);
            if ($validator->fails()) {
                $status = false;
                $fields['vat_certificate'] = [
                    'success' => false,
                    'message' => $validator->messages()->toArray(),
                ];
            } elseif ($request->file('vat_certificate') != null) {
                // Get File
                /** @var UploadedFile $vat_certificate */
                $vat_certificate = $request->file('vat_certificate');
                // Generate Random Name
                $file_name = Str::random(14).'_'.time().'.'.$vat_certificate->extension();
                // Set File Path
                $file_path = 'public/vat_certificates';
                // Save VAT Certificate
                $vat_certificate->storeAs($file_path, $file_name);
                // Save Path to VAT Certificate
                $company->vat_certificate = Storage::url($file_path.'/'.$file_name);
                $fields['vat_certificate'] = [
                    'success' => true,
                    'message' => 'Shop VAT Certificate has been updated.',
                ];
            }
        }

        // Validate and update is tax exempt
        if ($request->has('is_vat_exempt')) {
            $company->is_vat_exempt = filter_var($request->is_vat_exempt, FILTER_VALIDATE_BOOLEAN);
        }

        // Validate and upload VAT Certificate Verification
        if ($request->file('vat_verification')) {
            $validator = Validator::make($request->all(), [
                'vat_verification' => 'file|nullable',
            ]);
            if ($validator->fails()) {
                $status = false;
                $fields['vat_verification'] = [
                    'success' => false,
                    'message' => $validator->messages()->toArray(),
                ];
            } elseif ($request->file('vat_verification') != null) {
                // Get File
                /** @var UploadedFile $vat_verification */
                $vat_verification = $request->file('vat_verification');
                // Generate Random Name
                $file_name = Str::random(14).'_'.time().'.'.$vat_verification->extension();
                // Set File Path
                $file_path = 'public/vat_verifications';
                // Save VAT Certificate Verification
                $vat_verification->storeAs($file_path, $file_name);
                // Save Path to VAT Certificate Verification
                $company->vat_verification = Storage::url($file_path.'/'.$file_name);
                $fields['vat_verification'] = [
                    'success' => true,
                    'message' => 'Shop VAT Certificate Verification has been updated.',
                ];
            }
        }

        // Validate and update Address
        if ($request->address) {
            $user->branch->address = $request->address;
            $fields['address'] = [
                'success' => true,
                'message' => 'Address has been updated.',
            ];
            // Save Branch
            $user->branch->save();
        }

        // Update Round Off
        if ($request->has('round_off') && $request->round_off !== null) {
            $company->round_off = (bool) $request->round_off;
            $fields['round_off'] = [
                'success' => true,
                'message' => 'Round Off has been updated.',
            ];
            // Save Company
            $company->save();
        }

        // Billing Details

        // Is billing address same as postal address
        if ($request->is_billing_add_same_as_postal) {
            $is_billing_add_same_as_postal = (bool) $request->is_billing_add_same_as_postal;
            $prev_is_billing_add_same_as_postal = $user->company->is_billing_add_same_as_postal;
            // update is billing same  as postal
            $user->company->is_billing_add_same_as_postal = $is_billing_add_same_as_postal;
            // if new and previous values are difeerent,
            // then set the company billing address to the postal address
            if ($is_billing_add_same_as_postal != $prev_is_billing_add_same_as_postal) {
                $user->branch->address = $request->address;
                $user->branch->save();
            }
            $fields['is_billing_add_same_as_postal'] = [
                'success' => true,
                'message' => 'Billing Address is same to postal address has been updated.',
            ];
        }

        // Address
        if ($request->billing_address) {
            $company->billing_address = $request->billing_address;
            $fields['billing_address'] = [
                'success' => true,
                'message' => 'Billing Address has been updated.',
            ];
        }

        // City
        if ($request->billing_city_id) {
            $company->billing_city = $request->billing_city_id;
            $fields['billing_city'] = [
                'success' => true,
                'message' => 'Billing City has been updated.',
            ];
        }

        // State
        if ($request->billing_state_id) {
            $company->billing_state = $request->billing_state_id;
            $fields['billing_state'] = [
                'success' => true,
                'message' => 'Billing City has been updated.',
            ];
        }

        // Country
        if ($request->billing_country) {
            $company->billing_country = $request->billing_country;
            $fields['billing_country'] = [
                'success' => true,
                'message' => 'Billing Country has been updated.',
            ];
        }

        // Post Code
        if ($request->billing_post_code) {
            $company->billing_post_code = $request->billing_post_code;
            $fields['billing_post_code'] = [
                'success' => true,
                'message' => 'Billing Post Code has been updated.',
            ];
        }

        // Update App Config
        if ($request->app_config) {
            $old_app_config = json_decode($user->app_config ?? '{}');
            $new_app_config = json_decode($request->app_config);
            $user->app_config = json_encode((object) array_merge((array) $old_app_config, (array) $new_app_config));
            $fields['app_config'] = [
                'success' => true,
                'message' => 'App Config has been updated.',
            ];
        }

        // Update Device Token
        if ($request->device_token) {
            $user->device_token = $request->device_token;
            $fields['device_token'] = [
                'success' => true,
                'message' => 'Device Token has been updated.',
            ];
        }

        // Update Preferred Contact Time
        if ($request->preferred_contact_time) {
            $user->preferred_contact_time = $request->preferred_contact_time;
            $fields['preferred_contact_time'] = [
                'success' => true,
                'message' => 'Preferred Contact Time has been updated.',
            ];
        }

        // Save User
        $user->save();
        // Save Company
        $company->save();

        // Update User on Odoo
        //        $odoo = (new Odoo())->connect();
        //        $odoo->where('ref', $user->id)
        //            ->update('res.partner', $user->forOdoo());

        if (! $status) {
            return response()->json([
                'success' => false,
                'message' => 'There are some errors',
                'data' => ['errors' => $fields],
            ], 201);
        } else {
            if (! ($request->file('logo') || $request->file('cr_certificate') || $request->file('vat_certificate')) && ($company->status === COMPANY_STATUS_KYC && $company->cr != null && $company->cr_certificate != null && ($company->is_vat_exempt || ($company->vat_certificate != null && $company->vat != null)))) {
                $company->status = COMPANY_STATUS_REVIEW;
                $company->save();
                CrmLog::create([
                    'company_id' => $company->id,
                    'action' => 'Company status changed from KYC to In Review',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User has been updated successfully.',
                'data' => ['user' => new UserResource($user)],
            ], 201);
        }
    }

    /**
     * Delete the specified company.
     */
    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        if ($company->status == COMPANY_STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Active company cannot be deleted.',
                'data' => [],
            ], 201);
        }

        // This will also delete all of the related data of this company.
        // Check booted method inside Company class
        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully.',
            'data' => [],
        ]);
    }

    /**
     * Change admin staff assigned to a company.
     */
    public function changeAdminStaff(Company $company, User $user): JsonResponse
    {
        $this->authorize('changeAdminStaff', $company);

        $company->admin_staff_id = $user->id;
        $company->save();

        $user->notify(new CompanyAdminStaffChanged($company));

        return response()->json([
            'success' => true,
            'message' => 'Company CSR updated successfully.',
            'data' => [],
        ]);
    }

    /**
     * Change reseller assigned to a company.
     */
    public function changeReseller(Request $request, int|string $id): JsonResponse
    {
        // Validate the request data
        $request->validate([
            'reseller_number' => 'required|string|exists:users,reseller_number', // Assuming 'users' table has 'reseller_number' column
        ]);

        // Find the company by its ID
        $company = Company::findOrFail($id);

        // Update the reseller_number
        $company->reseller_number = $request->input('reseller_number');

        // Save the changes to the database
        $company->save();

        // Return a response
        return response()->json([
            'success' => true,
            'message' => 'Reseller number updated successfully.',
            'company' => new CompanyResource($company),
        ]);
    }

    /**
     * Update company details on Odoo.
     */
    public function updateCompanyOnOdoo(Company $company): JsonResponse
    {
        CompanyActivated::dispatch($company);

        return response()->json([
            'success' => true,
            'message' => 'Company will be updated on Odoo in background.',
            'data' => [],
        ]);
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
