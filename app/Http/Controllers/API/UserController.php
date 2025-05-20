<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\UploadedFile;
use App\Events\CompanyUserForOdooCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\UserResource;
use App\Models\Balance;
use App\Models\CrmLog;
use App\Models\Invoice;
use App\Models\SubscriptionUserLicense;
use App\Models\User;
use Illuminate\Http\JsonResponse;
//use Edujugon\Laradoo\Odoo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Image;

/**
 * @group Customer
 *
 * @subgroup User
 *
 * @subgroupDescription APIs for managing User
 */
class UserController extends Controller
{
    protected ?User $loggedInUser;

    public function __construct()
    {
        $this->loggedInUser = auth()->guard('api')->user();
    }

    /**
     * Update company details.
     *
     * @bodyParam first_name string optional The first name of the user. Example: John
     * @bodyParam last_name string optional The last name of the user. Example: Doe
     * @bodyParam password string optional The password for the user account. Must be confirmed and at least 6 characters. Example: secret123
     * @bodyParam business_name string optional The name of the business. Example: My Business
     * @bodyParam business_type string optional The type of business. Example: Retail
     * @bodyParam cr string optional The commercial registration number. Example: 1234567890
     * @bodyParam cr_certificate file optional The commercial registration certificate file. Accepted types: jpg, jpeg, png, pdf, doc, docx. Max size: defined by CR_VAT_FILE_SIZE
     * @bodyParam vat string optional The VAT number. Example: 123456789012345
     * @bodyParam vat_certificate file optional The VAT certificate file. Accepted types: jpg, jpeg, png, pdf, doc, docx. Max size: defined by CR_VAT_FILE_SIZE
     * @bodyParam address string optional The address of the company. Example: 123 Main St
     * @bodyParam round_off boolean optional Indicates if rounding off is enabled. Example: true
     * @bodyParam billing_address string optional The billing address of the company. Example: 123 Billing St
     * @bodyParam billing_city_id int optional The ID of the billing city. Example: 1
     * @bodyParam billing_state_id int optional The ID of the billing state. Example: 1
     * @bodyParam billing_country string optional The billing country. Example: USA
     * @bodyParam billing_post_code string optional The postal code for billing. Example: 12345
     * @bodyParam is_billing_add_same_as_postal boolean optional Indicates if the billing address is the same as the postal address. Example: false
     * @bodyParam app_config string optional JSON string of app configuration. Example: {"theme":"dark"}
     * @bodyParam device_token string optional The device token for push notifications. Example: abcdef12345
     * @bodyParam preferred_contact_time string optional The preferred contact time. Example: Afternoon
     * @bodyParam is_vat_exempt boolean optional Indicates if the company is exempt from VAT. Example: false
     * @bodyParam logo file optional The company logo file. Accepted types: jpg, jpeg, png. Max size: defined by LOGO_SIZE
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('updateUserCompany', User::class);

        // TODO: Implement More Validations on Some Fields
        // Edit only if User is admin or Want's to manage his/her own profile
        $user = $this->loggedInUser;

        if ($user->company->is_onboarding_complete == false) {
            $user->company->is_onboarding_complete = true;
            $user->company->save();
        }

        if (
            empty($request->first_name) && empty($request->last_name) /*&& empty($request->phone)*/ && empty($request->password)
            && empty($request->file('logo')) && empty($request->business_name)
            && empty($request->business_type)
            && empty($request->cr) && empty($request->cr_certificate)
            && empty($request->vat) && empty($request->vat_certificate)
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
        ) {
            return response()->json([
                'success' => false,
                'message' => 'No Valid data to process, add some data.',
                'data' => [],
            ], 400);
        }

        $status = true;
        $fields = [];

        if ($user->company && $user->company->status == COMPANY_STATUS_KYC) {
            // Validate and First Name
            if ($request->first_name) {
                $validator = Validator::make($request->all(), [
                    'first_name' => 'required|string|min:3',
                ]);
                if ($validator->fails()) {
                    $status = false;
                    $fields += $validator->messages()->toArray();
                } else {
                    $user->first_name = $request->first_name;
                }
            }

            // Validate and Last Name
            if ($request->last_name) {
                $validator = Validator::make($request->all(), [
                    'last_name' => 'required|string|min:3',
                ]);
                if ($validator->fails()) {
                    $status = false;
                    $fields += $validator->messages()->toArray();
                } else {
                    $user->last_name = $request->last_name;
                }
            }

            // Validate and update Phone Number
            //        if ($request->phone) {
            //            $validator = Validator::make($request->all(), [
            //                'phone' => 'required|string|min:10'
            //            ]);
            //            if ($validator->fails()) {
            //                $status = false;
            //                $fields += $validator->messages()->toArray();
            //            } else {
            //                $user->phone = $request->phone;
            //            }
            //        }

            // Validate and update Password
            if ($request->password) {
                $validator = Validator::make($request->all(), [
                    'password' => 'required|min:6|confirmed',
                ]);
                if ($validator->fails()) {
                    $status = false;
                    $fields += $validator->messages()->toArray();
                } else {
                    $user->password = bcrypt($request->password);
                }
            }

            // Validate and update Shop Name
            if ($request->business_name) {
                $validator = Validator::make($request->all(), [
                    'business_name' => 'required|string|min:3',
                ]);
                if ($validator->fails()) {
                    $status = false;
                    $fields += $validator->messages()->toArray();
                } else {
                    $user->company->name = $request->business_name;
                }
            }

            // Validate and update Business Type
            if ($request->business_type) {
                $user->company->business_type = $request->business_type;
            }

            // Validate and update CR No.
            if ($request->cr) {
                $validator = Validator::make($request->all(), [
                    'cr' => 'string|min:10|max:10|unique:companies,cr,NULL,id,deleted_at,NULL',
                ]);
                if ($validator->fails()) {
                    $status = false;
                    $fields += $validator->messages()->toArray();
                } else {
                    $user->company->cr = $request->cr;
                }
            }

            // Validate and update CR Certificate
            if ($request->file('cr_certificate')) {
                $validator = Validator::make($request->all(), [
                    'cr_certificate' => [
                        'required',
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
                    $fields += $validator->messages()->toArray();
                } else {
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
                    $user->company->cr_certificate = Storage::url($file_path.'/'.$file_name);
                }
            }

            // Validate and update VAT No.
            if ($request->vat) {
                $validator = Validator::make($request->all(), [
                    'vat' => 'string|min:15|max:15|unique:companies,vat,NULL,id,deleted_at,NULL',
                ]);
                if ($validator->fails()) {
                    $status = false;
                    $fields += $validator->messages()->toArray();
                } else {
                    $user->company->vat = $request->vat;
                }
            }

            // Validate and update VAT Certificate
            if ($request->file('vat_certificate')) {
                $validator = Validator::make($request->all(), [
                    'vat_certificate' => [
                        'required',
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
                    $fields += $validator->messages()->toArray();
                } else {
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
                    $user->company->vat_certificate = Storage::url($file_path.'/'.$file_name);
                }
            }

            // Validate and update is tax exempt
            if ($request->has('is_vat_exempt')) {
                $user->company->is_vat_exempt = filter_var($request->is_vat_exempt, FILTER_VALIDATE_BOOLEAN);
            }
        }

        // Validate and update Address
        if ($request->address) {
            $user->branch->address = $request->address;
            // Save Branch
            $user->branch->save();
        }

        // Update Round Off
        if ($request->has('round_off') && $request->round_off !== null) {
            $user->company->round_off = $request->round_off;
            // Save Company
            $user->company->save();
        }

        // Update App Config
        if ($request->app_config) {
            $old_app_config = json_decode($user->app_config ?? '{}');
            $new_app_config = json_decode($request->app_config);
            $user->app_config = json_encode((object) array_merge((array) $old_app_config, (array) $new_app_config));
        }

        // Update Device Token
        if ($request->device_token) {
            $user->device_token = $request->device_token;
        }

        // Validate and update Logo
        if ($request->file('logo')) {
            $validator = Validator::make($request->all(), [
                'logo' => [
                    'required',
                    File::image()
                        ->max(LOGO_SIZE * 1024),
                ],
            ]);
            if ($validator->fails()) {
                $status = false;
                $fields += $validator->messages()->toArray();
            } else {
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
                $user->company->logo = Storage::url($access_path.'/'.$file_name);
                // $user->logo = $request->file('logo')->store('public/shop_logos');
            }
        }

        // Billing Details
        // Is billing address same as postal address
        $prev_is_billing_add_same_as_postal = (bool) $user->company->is_billing_add_same_as_postal;
        if ($request->has('is_billing_add_same_as_postal') && filter_var($request->is_billing_add_same_as_postal, FILTER_VALIDATE_BOOL) !== $prev_is_billing_add_same_as_postal) {
            $is_billing_add_same_as_postal = filter_var($request->is_billing_add_same_as_postal, FILTER_VALIDATE_BOOLEAN);
            // update is billing same  as postal
            $user->company->is_billing_add_same_as_postal = $is_billing_add_same_as_postal;
            // if new and previous values are difeerent,
            // then set the company billing address to the postal address
            if ($is_billing_add_same_as_postal === true && $is_billing_add_same_as_postal != $prev_is_billing_add_same_as_postal) {
                $user->branch->address = $request->address;
                $user->branch->save();
            }
        }

        // Address
        if ($request->billing_address) {
            $user->company->billing_address = $request->billing_address;
        }

        // City
        if ($request->billing_city_id) {
            $user->company->billing_city = $request->billing_city_id;
        }

        // State
        if ($request->billing_state_id) {
            $user->company->billing_state = $request->billing_state_id;
        }

        // Country
        if ($request->billing_country) {
            $user->company->billing_country = $request->billing_country;
        }

        // Post Code
        if ($request->billing_post_code) {
            $user->company->billing_post_code = $request->billing_post_code;
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

        if ($user->company) {
            // Save Company
            $user->company->save();
        }

        // Update User on Odoo
        //        $odoo = (new Odoo())->connect();
        //        $odoo->where('ref', $user->id)
        //            ->update('res.partner', $user->forOdoo());

        CompanyUserForOdooCreated::dispatchIf($this->loggedInUser->company->hasOdooIntegration(), $user);

        if (! $status) {
            return response()->json([
                'success' => false,
                'message' => 'There are some errors',
                'data' => ['errors' => $fields],
            ], 201);
        } else {
            // if no logo, cr_certificate or vat_certificate
            if (! ($request->file('logo') || $request->file('cr_certificate') || $request->file('vat_certificate')) && ($user->company && $user->company->status === COMPANY_STATUS_KYC && $user->company->cr != null && $user->company->cr_certificate != null && ($user->company->is_vat_exempt || ($user->company->vat_certificate != null && $user->company->vat != null)))) {
                $user->company->status = COMPANY_STATUS_REVIEW;
                $user->company->save();
                CrmLog::create([
                    'company_id' => $user->company->id,
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
     * Update user app config
     *
     * @bodyParam app_config string optional JSON string of app configuration. Example: {"theme":"dark"}
     */
    public function updateUserAppConfig(Request $request): JsonResponse
    {
        $user = $this->loggedInUser;
        if ($request->app_config) {
            $old_app_config = json_decode($user->app_config ?? '{}');
            $new_app_config = json_decode($request->app_config);
            $user->app_config = json_encode((object) array_merge((array) $old_app_config, (array) $new_app_config));
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'User app config has been updated successfully.',
            'data' => ['user' => new UserResource($user)],
        ], 201);
    }

    /**
     * Get all employees.
     */
    public function indexEmployees(): JsonResponse
    {
        $this->authorize('viewAnyEmployees', User::class);

        $owner = User::where('type', USER_TYPE_BUSINESS_OWNER)
            ->where('company_id', $this->loggedInUser->company_id)
            ->get();
        $employees = User::where('type', USER_TYPE_EMPLOYEE)
            ->where('company_id', $this->loggedInUser->company_id)
            ->get();
        $employees = $employees->sortBy('name')->keyBy->id;

        return response()->json([
            'success' => true,
            'message' => 'Employees List Response',
            'data' => [
                'owner' => UserResource::collection($owner),
                'employees' => UserResource::collection($employees),
            ],
        ], 200);
    }

    /**
     * Store an employee.
     */
    public function storeEmployees(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('createEmployee', User::class);

        $employee = $this->manageEmployee($request, new User);
        $this->logEmployeeCreation('added');

        CompanyUserForOdooCreated::dispatchIf($this->loggedInUser->company->hasOdooIntegration(), $employee);

        return $this->employeeResponse($employee, 'Employee has been created successfully.');
    }

    /**
     * Update employee.
     */
    public function updateEmployees(UpdateEmployeeRequest $request, User $employee): JsonResponse
    {
        if ($employee->type === USER_TYPE_BUSINESS_OWNER) {
            $this->authorize('updateEmployeeOurself', $employee);
        } elseif ($employee->type === USER_TYPE_EMPLOYEE) {
            $this->authorize('updateEmployee', $employee);
        }
        

        $employee = $this->manageEmployee($request, $employee);
        $this->logEmployeeCreation('updated');

        CompanyUserForOdooCreated::dispatchIf($this->loggedInUser->company->hasOdooIntegration(), $employee);

        return $this->employeeResponse($employee, 'Employee has been updated successfully.');
    }

    /**
     * Toggle employee as a machine user.
     */
    public function toggleEmployeeMachineUser(Request $request, User $employee): JsonResponse
    {
        $this->authorize('updateEmployee', $employee);

        $machine_employees_count = $this->loggedInUser->company->machineUserEmployees()->count();
        if ($this->loggedInUser->company->devices()->count() <= $machine_employees_count) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have available device to assign to this employee.',
                'data' => [],
            ], 403);
        }
        // updated value of is_machine_user will be the inverse of the present value
        $request->is_machine_user = ! $employee->is_machine_user;
        // Set machine user employee
        $this->setMachineUserEmployee($employee, $request);

        return response()->json([
            'success' => true,
            'message' => 'Employee has been updated successfully.',
            'data' => [
                'employee' => new UserResource($employee),
            ],
        ], 201);
    }

    /**
     * Activate employee.
     */
    public function activateEmployees(Request $request, User $employee): JsonResponse
    {
        $this->authorize('updateEmployee', $employee);

        if ($this->loggedInUser->company->activeEmployees()->count() < $this->loggedInUser->company->active_subscription->user_licenses_count) {
            $employee->is_active = true;
            $employee->save();

            CrmLog::create([
                'company_id' => $this->loggedInUser->company_id,
                'action' => 'Company activated an employee',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee has been updated successfully.',
                'data' => [
                    'employee' => new UserResource($employee),
                ],
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => 'You need to buy a user license to activate another employee.',
            'data' => [],
        ], 201);
    }

    /**
     * Deactivate employee.
     */
    public function deactivateEmployees(Request $request, User $employee): JsonResponse
    {
        $this->authorize('updateEmployee', $employee);

        $employee->is_active = false;
        $employee->save();

        CrmLog::create([
            'company_id' => $this->loggedInUser->company_id,
            'action' => 'Company deactivated an employee',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employee has been updated successfully.',
            'data' => [
                'employee' => new UserResource($employee),
            ],
        ], 201);
    }

    /**
     * Delete employee.
     */
    public function deleteEmployee(Request $request, User $employee): JsonResponse
    {
        $this->authorize('updateEmployee', $employee);

        $employee->delete();

        CrmLog::create([
            'company_id' => $this->loggedInUser->company_id,
            'action' => 'Company deleted an employee',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Employee has been deleted successfully.',
            'data' => [],
        ], 201);
    }

    // TODO: Work in progress
    // public function loadBalance(Request $request)
    // {
    //     $request->validate([
    //         'balance' => 'required|integer|min:0',
    //     ]);
    //     $user = auth()->guard('api')->user();
    //     $company = $user?->company;
    //     if ($user->type === USER_TYPE_BUSINESS_OWNER) {
    //         $active_subscription = $company->active_subscription;

    //         // Check if the active subscription is a daily subscription
    //         if ($active_subscription->type === PLAN_TYPE_DAILY && $active_subscription->is_trial === BOOLEAN_FALSE) {
    //             // Create a new SubscriptionUserLicense
    //             $subscription_user_license = new SubscriptionUserLicense();
    //             $subscription_user_license->amount = $request->balance;
    //             $subscription_user_license->company_id = $company->id;
    //             $subscription_user_license->subscription_id = $active_subscription->id;
    //             $subscription_user_license->status = BOOLEAN_TRUE;
    //             $subscription_user_license->save();

    //             // Add a record to the Balance table
    //             $company_subscription_balance = new Balance();
    //             $company_subscription_balance->company_id = $company->id;
    //             $company_subscription_balance->amount = $request->balance;
    //             $company_subscription_balance->save();

    //             // Generate an invoice
    //             Invoice::generateInvoice($subscription_user_license, INVOICE_TYPE_LICENSE, $request->balance);

    //             // Create a CRM log entry
    //             CrmLog::create([
    //                 'company_id' => $company->id,
    //                 'action' => 'Load balance for company',
    //             ]);

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Balance loaded successfully.',
    //             ], 200);
    //         }
    //     }

    //     return response()->json([
    //         'success' => false,
    //         'message' => 'Active subscription is not a daily subscription.',
    //     ], 400);
    // }

    /**
     * Send to Odoo.
     */
    public function sendToOdoo(User $employee): JsonResponse
    {
        $this->authorize('sendEmployeeToOddo', $employee);

        CompanyUserForOdooCreated::dispatchIf($this->loggedInUser->company->hasOdooIntegration(), $employee);

        return response()->json([
            'success' => true,
            'message' => 'Company user is being sent to Odoo.',
            'data' => [],
        ], 200);
    }

    private function manageEmployee(Request $request, User $employee): User
    {
        $employee->fill($request->except(['password', 'is_waiter', 'allow_editable_price']));
        if ($request->password) {
            $employee->password = bcrypt($request->password);
        }
        $employee->app_config = $this->getAppConfig($request, $employee);
        $employee->company_id = $this->loggedInUser->company_id;
        // $employee->type = USER_TYPE_EMPLOYEE;
        // $employee->type = $employee->type;
        // Check if the type is null
        $employee->type = $employee->type ?? USER_TYPE_EMPLOYEE;
        $employee->is_waiter = $this->shouldSetWaiter($request);
        $employee->save();

        $this->setMachineUserEmployee($employee, $request);

        return $employee;
    }

    private function logEmployeeCreation(string $action): void
    {
        CrmLog::create([
            'company_id' => $this->loggedInUser->company_id,
            'action' => "Company {$action} an employee",
        ]);
    }

    private function getAppConfig(Request $request, ?User $employee = null): string
    {
        $oldAppConfig = json_decode($employee->app_config, true) ?? [];
        $newAppConfig = [
            'direction' => 'ltr',
            'allowEditablePrice' => $request->allow_editable_price ?? false,
        ];

        return json_encode(array_merge($oldAppConfig, $newAppConfig)) ?: $employee->app_config;
    }

    private function shouldSetWaiter(Request $request): bool
    {
        return (hasActiveWaiterManagementAddon($this->loggedInUser) || hasActiveJobManagementAddon($this->loggedInUser)) && (bool) $request->is_waiter;
    }

    private function employeeResponse(User $employee, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'employee' => new UserResource($employee),
            ],
        ], 201);
    }

    // Set is_machine_user property if the company has active annual subscription of devices
    // And the number of devices is greater than the number of employees that are machine users
    private function setMachineUserEmployee(User $employee, Request $request): void
    {
        $active_subscription = $this->loggedInUser->company->active_subscription;
        if (
            $active_subscription &&
            $active_subscription->type == PLAN_TYPE_PRO &&
            $active_subscription->is_trial == BOOLEAN_TRUE &&
            $active_subscription->validity_days > 90
        ) {
            $machine_employees_count = $this->loggedInUser->company->machineUserEmployees()->count();
            if ($request->is_machine_user == BOOLEAN_FALSE || $this->loggedInUser->company->devices()->count() > $machine_employees_count) {
                $employee->is_machine_user = $request->is_machine_user;
                $employee->save();
            }
        }
    }
}
