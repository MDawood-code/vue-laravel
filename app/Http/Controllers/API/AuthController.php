<?php

namespace App\Http\Controllers\API;

use Laravel\Passport\Token;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginUserRequest;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Http\Requests\Auth\ValidateRegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Traits\FileUploadTrait;
use App\Http\Traits\SMSTrait;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyAddon;
use App\Models\OTPSMS;
use App\Enums\AddonName;
use App\Models\ReferralCampaign;
//use Edujugon\Laradoo\Odoo;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionUserLicense;
use App\Models\User;
use App\Models\Addon;
use App\Notifications\CompanyCreated;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;

/**
 * @group Customer
 *
 * @subgroup Auth
 *
 * @subgroupDescription APIs for managing Auth
 */
class AuthController extends Controller
{
    use FileUploadTrait;
    use SMSTrait;

    /**
     * Register user.
     *
     * @unauthenticated
     */
    public function register(RegisterUserRequest $request): JsonResponse
    {
        // if local or testing environment, verify hadrd coded otp
        if (App::environment('local')) {
            if ($request->otp != '1234') {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP validation Failed.',
                    'data' => [
                        'errors' => [],
                    ],
                ], 400);
            }
        } else {
            // else verify real otp
            $otp_sms = OTPSMS::where('number', $request->phone)->first();
            if (! $otp_sms || $otp_sms->code !== $request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'OTP validation Failed.',
                    'data' => [
                        'errors' => [],
                    ],
                ], 400);
            }
        }

        $data = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'type' => $request->type,
            'password' => bcrypt($request->password),
            'phone' => $request->phone,
            'app_config' => '{"direction":"ltr", "allowEditablePrice": true}',
            'device_token' => $request->device_token ?? '',
            // 'referral_code' => $request->referral_code ?? '',
            'preferred_contact_time' => in_array($request->preferred_contact_time, ['09:00 AM - 02:00 PM', '02:00 PM - 05:00 PM']) ? $request->preferred_contact_time : null,
            'can_add_edit_product' => true,
            'can_add_edit_customer' => false,
            'can_add_pay_sales_invoice' => false,
            'can_view_sales_invoice' => true,
            'can_view_customer' => true,
            // 'can_edit_product' => true,
            'can_refund_transaction' => true,
            'can_request_stock_adjustment' => true,
        ];
        if ($request->type == USER_TYPE_RESELLER) {
            // Extract first name from the request and get the first 5 characters
            $firstName = $request->input('first_name');
            $currentDate = Carbon::now()->toDateString();
            // Generate a random 4-digit number
            $randomNumber = mt_rand(1000, 9999);

            // Combine first name part and random number to form reseller number
            $resellerNumber = $firstName.$randomNumber;
            $data['status'] = RESELLER_STATUS_KYC;
            $data['reseller_number'] = $resellerNumber;
            $data['user_type'] = $request->user_type;
            $data['reseller_level_change_at'] = $currentDate;

            // Handling the User photo upload
            if ($request->hasFile('user_photo_id')) {
                $file = $request->file('user_photo_id');

                if ($file instanceof UploadedFile) {
                    $data['user_photo_id'] = $this->uploadFile($file, 'user_images');
                } else {
                    return response()->json(['error' => 'Invalid file upload.'], 400);
                }
            }
            // Handling the company registration document upload
            if ($request->hasFile('company_registration_document')) {
                $file = $request->file('company_registration_document');

                if ($file instanceof UploadedFile) {
                    $data['company_registration_document'] = $this->uploadFile($file, 'reseller_company_registration');
                    $data['reseller_company_name'] = $request->reseller_company_name;
                } else {
                    return response()->json(['error' => 'Invalid file upload.'], 400);
                }
            }
            $data['reseller_level'] = 'Basic';
        }
        $user = User::create($data);
        // Getting Subscription Request Variables
        // Initially we had two types of plans: basic and pro
        // But now we only have pro plan for this instance
        // $plan_type = $request->type ?? PLAN_TYPE_BASIC;
        if ($request->type != USER_TYPE_RESELLER) {
            $plan_type = PLAN_TYPE_PRO;
            $subscription_scheme = getSystemSubscriptionScheme();
            $plan_type = $subscription_scheme && $subscription_scheme === 'daily' ? PLAN_TYPE_DAILY : PLAN_TYPE_PRO;
            $plan_period = $subscription_scheme && $subscription_scheme === 'daily' ? PERIOD_DAILY : ($request->period ?? PERIOD_MONTHLY);
            $users_count = $request->users_count ?? 1;

            // Need previously created company in a later step
            $prev_company = Company::latest()->first();

            // Create Company
            $addons = ['pos'];
            if ($plan_type === PLAN_TYPE_PRO) {
                $addons[] = 'multi_branch';
            }
            
            $company = new Company;
            $company->addons = json_encode($addons) !== false ? json_encode($addons) : null;
            $company->name = $request->business_name;
            $company->referral_code = $request->referral_code ?? '';
            $company->reseller_number = $request->reseller_number ?? '';
            $company->save();
            // Create Empty Branch
            $branch = new Branch;
            $branch->name = 'MAIN';
            $branch->company_id = $company->id;
            $branch->save();

            // Update Company ID in User;
            $user->company_id = $company->id;
            $user->branch_id = $branch->id;
            $user->save();

            $selected_plan = SubscriptionPlan::where('type', $plan_type)
                ->where('period', $plan_period)
                ->where('is_trial', BOOLEAN_FALSE)
                ->first();

            // Add Subscription
            if ($plan_type === PLAN_TYPE_DAILY) {
                $days = 90; //90 for 90 days
                // For daily subscription, discount is in percentage because the amount is not fixed.
                $plan_amount = ($selected_plan->price - ($selected_plan->price * $selected_plan->discount / 100));
                $license_discount = $selected_plan->user_price * $selected_plan->user_discount / 100;

                $subscription = new Subscription;
                $subscription->name = $selected_plan->name;
                $subscription->type = $selected_plan->type;
                $subscription->period = $selected_plan->period;
                $subscription->amount = $plan_amount;
                $subscription->license_amount = $selected_plan->user_price;
                $subscription->license_discount = $license_discount;
                $subscription->balance = 0.0; //because it is trial, no amount is deducted from user
                $subscription->is_trial = BOOLEAN_TRUE;
                $subscription->validity_days = 90;
                $subscription->company_id = $company->id;
                $subscription->status = BOOLEAN_TRUE;
                $subscription->save();

                // Add Subscription User Licenses
                $subscription_user_license = new SubscriptionUserLicense;
                $subscription_user_license->quantity = $users_count;
                $subscription_user_license->amount = ($plan_amount + ($selected_plan->user_price - $license_discount) * $users_count) * $days;
                $subscription_user_license->company_id = $company->id;
                $subscription_user_license->subscription_id = $subscription->id;
                $subscription_user_license->status = BOOLEAN_TRUE;
                $subscription_user_license->save();
            } else {
                $subscription = new Subscription;
                $subscription->name = $selected_plan->name;
                $subscription->type = $selected_plan->type;
                $subscription->period = $selected_plan->period;
                $subscription->amount = $selected_plan->price - $selected_plan->discount;
                $subscription->license_amount = $selected_plan->user_price;
                $subscription->license_discount = $selected_plan->user_discount;
                $subscription->is_trial = BOOLEAN_TRUE;
                $subscription->validity_days = 90;
                $subscription->company_id = $company->id;
                $subscription->status = BOOLEAN_TRUE;
                $subscription->save();

                if ($selected_plan->type === PLAN_TYPE_PRO) {
                    // Add Subscription User Licenses
                    $subscription_user_license = new SubscriptionUserLicense;
                    $subscription_user_license->quantity = $users_count;
                    $subscription_user_license->amount = ($selected_plan->user_price - $selected_plan->user_discount) * $users_count;
                    $subscription_user_license->company_id = $company->id;
                    $subscription_user_license->subscription_id = $subscription->id;
                    $subscription_user_license->status = BOOLEAN_TRUE;
                    $subscription_user_license->save();
                }
            }

            // Send New Register User to Odoo
            //        $odoo = (new Odoo())->connect();
            //        $odoo_user_id = $odoo->create('res.partner', $user->forOdoo());

            // Find next admin staff whom this company is to be assigned
            if ($prev_company) {
                // Find next admin staff
                $next_staff = User::where('type', USER_TYPE_ADMIN_STAFF)->where('can_manage_all_regions', BOOLEAN_TRUE)->where('id', '>', $prev_company->admin_staff_id ?? 0)->orderBy('id', 'asc')->first();
                if ($next_staff) {
                    $company->admin_staff_id = $next_staff->id;
                } else {
                    $staff = User::where('type', USER_TYPE_ADMIN_STAFF)->where('can_manage_all_regions', BOOLEAN_TRUE)->first();
                    $company->admin_staff_id = $staff ? $staff->id : null;
                }
            } else {
                $staff = User::where('type', USER_TYPE_ADMIN_STAFF)->where('can_manage_all_regions', BOOLEAN_TRUE)->first();
                $company->admin_staff_id = $staff ? $staff->id : null;
            }
            $company->save();

            // $access_token = $user->createToken('authToken')->accessToken;
            // Annouce event: company created
            // Currently sending email to hi email address
            Notification::send(
                User::where('type', USER_TYPE_ADMIN)
                    ->orWhere('type', USER_TYPE_SUPER_ADMIN)
                    ->orWhere('id', $company->adminStaff?->id)
                    ->get(),
                new CompanyCreated($company)
            );

            $company->crmLogs()->create([
                'action' => 'New customer signup',
            ]);

            $company->balance()->create();
        }
          // Subscribe to addons
          if ($request->has('selected_addons')) {
            // Decode JSON string into an array
            $selectedAddons = json_decode($request->selected_addons, true);
        
            if (!is_array($selectedAddons)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid format for selected_addons',
                ], 400);
            }
        
            foreach ($selectedAddons as $addon) {
                $addonId = is_array($addon) ? $addon['id'] : $addon;
                // Use findOrFail to ensure the addon exists
                $addon = Addon::findOrFail($addonId);
        
                if ($addon instanceof Addon) {
                    $this->subscribeAddon($user, $addon);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid Id',
                    ], 400);
                }
            }
        }
        
        $access_token = $user->createToken('authToken')->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Registration Successful.',
            'data' => [
                'user' => new UserResource($user, $access_token, true),
            ],
        ], 201);
    }
    private function subscribeAddon(User $user, Addon $addon): void
    {
        $company = $user->company;
        // Get previous addon subscription if exists
        $prevCompanyAddon = $company->addons()->where('addon_id', $addon->id)->latest()->first();
        if ($prevCompanyAddon) {
            $trialValidityDays = $prevCompanyAddon->trial_validity_days;
            $trialStartedAt = $prevCompanyAddon->trial_started_at;
            $trialEndedAt = $prevCompanyAddon->trial_ended_at;
        } else {
            $trialValidityDays = $addon->trial_validity_days;
            $trialStartedAt = $addon->trial_validity_days === 0 ? null : Carbon::now()->toDateString();
            $trialEndedAt = $addon->trial_validity_days === 0 ? null : Carbon::now()->addDays($addon->trial_validity_days)->toDateString();
        }
        $companyAddon = new CompanyAddon;
        $companyAddon->addon_id = $addon->id;
        $companyAddon->company_id = $company->id;
        $companyAddon->price = $addon->price;
        $companyAddon->discount = $addon->discount;
        $companyAddon->trial_validity_days = $trialValidityDays;
        $companyAddon->trial_started_at = $trialStartedAt;
        $companyAddon->trial_ended_at = $trialEndedAt;
        $subscriptionScheme = getSystemSubscriptionScheme();
        if ($subscriptionScheme && $subscriptionScheme === 'daily') {
            $companyAddon->status = BOOLEAN_TRUE;
            $companyAddon->start_date = Carbon::now()->toDateString();
            $companyAddon->end_date = null;
        } else {
            $companyAddon->status = BOOLEAN_FALSE;
        }
        $companyAddon->save();
    
    }
    
 
    /**
     * Validate registeration data.
     *
     * @unauthenticated
     */
    public function registerValidate(ValidateRegisterRequest $request): JsonResponse
    {
        if ($request->referral_code != null) {
            $referralCodeCheck = $this->checkReferralCode(new Request(['referral_code' => $request->referral_code]));
            if ($referralCodeCheck->getStatusCode() !== 200) {
                return $referralCodeCheck;
            }
        }
        if ($request->reseller_number != null) {
            $resellerNumberCheck = $this->checkResellerNumber(new Request(['reseller_number' => $request->reseller_number]));
            if ($resellerNumberCheck->getStatusCode() !== 200) {
                return $resellerNumberCheck;
            }
        }
        if (App::environment('local')) {
            return $this->fakeOTPSMS();
        }

        return $this->sendOTPSMS(new Request(['number' => $request->phone]));
    }

    /**
     * Referral Code Validation.
     */
    public function checkReferralCode(Request $request): JsonResponse
    {
        $referralCode = $request->referral_code;
        $campaign = ReferralCampaign::where('referral_code', $referralCode)->first();
        if ($campaign) {
            $expiryDate = Carbon::parse($campaign->expiry_date);
            if ($expiryDate->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Referral Code Expired.',
                    'data' => [],
                ], 400);
            }
            if ($campaign->status == BOOLEAN_FALSE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Referral Code InActive.',
                    'data' => [],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Referral Code Valid.',
                'data' => [],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Referral Code Not Exist.',
                'data' => [],
            ], 400);
        }
    }

    /**
     * Reseller Number Validation.
     */
    public function checkResellerNumber(Request $request): JsonResponse
    {
        $resellerNumber = $request->reseller_number;
        $resellerN = User::where('reseller_number', $resellerNumber)->first();
        if ($resellerN) {
            if ($resellerN->is_active == BOOLEAN_FALSE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reseller InActive.',
                    'data' => [],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Reseller Code Valid.',
                'data' => [],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Reseller Code Not Exist.',
                'data' => [],
            ], 400);
        }
    }

    /**
     * Login user.
     *
     * @unauthenticated
     */
    public function login(LoginUserRequest $request): JsonResponse
    {
        $credentials = $request->only(['phone', 'password']);
        $user = User::where('phone', $credentials['phone'])->first();

        if (! auth()->attempt($credentials)) {
            $responseMessage = 'Invalid Phone / Password Combination.';

            return response()->json([
                'success' => false,
                'message' => $responseMessage,
                'data' => ['error' => $responseMessage],
            ], 422);
        }

        // TODO: Check here if Employee and is Inactive

        if (! $user->is_active && $user->type === USER_TYPE_EMPLOYEE) {
            $responseMessage = 'Your Account is deactivated. Kindly contact your manager/administrator.';

            return response()->json([
                'success' => false,
                'message' => $responseMessage,
                'data' => ['error' => $responseMessage],
            ], 422);
        }

        if ($user->type !== USER_TYPE_ADMIN && $user->type !== USER_TYPE_SUPER_ADMIN) {
            // Revoke All Previous Tokens for this User
            /** @var Token $token */
            foreach ($user->tokens as $token) {
                $token->revoke();
            }
        }

        // Update Device Token if found
        if (! empty($request->device_token)) {
            $user->device_token = $request->device_token;
            $user->save();
        }

        $access_token = auth()->user()->createToken('authToken')->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Login Successful.',
            'data' => [
                'user' => new UserResource($user, $access_token, true),
            ],
        ], 200);
    }

    /**
     * Check if user is logged in.
     *
     * @unauthenticated
     */
    public function isLogin(): JsonResponse
    {
        $user = auth()->guard('api')->user();

        if (! empty($user)) {
            $success = true;
            $responseMessage = 'User is logged in.';
            $data = ['user' => new UserResource($user, '', true)];
        } else {
            $success = false;
            $responseMessage = 'User is not logged in.';
            $data = [];
        }

        return response()->json([
            'success' => $success,
            'message' => $responseMessage,
            'data' => $data,
        ], 200);
    }

    /**
     * Logout user.
     */
    public function logout(): JsonResponse
    {
        $user = auth()->guard('api')->user();

        // Revoke All User Tokens
        /** @var Token $token */
        foreach ($user->tokens as $token) {
            $token->revoke();
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
            'data' => [],
        ], 200);
    }

    /**
     * Send OTP sms.
     *
     * @unauthenticated
     */
    public function sendOTPSMS(Request $request): JsonResponse
    {

        $number = $request->number;
        $code = mt_rand(1000, 9999);
        $text = urlencode('OTP Code for any pos is '.$code);

        $otp_sms = OTPSMS::where('number', $number)->first();
        if (! $otp_sms) {
            $otp_sms = new OTPSMS;
            $otp_sms->try = 1;
        } else {
            $otp_sms->try += 1;
        }

        if ($otp_sms->try > 20) {
            // TODO: Check for DateTime Last Try
            return response()->json([
                'success' => false,
                'message' => 'Limit Reached.',
                'data' => [],
            ], 400);
        }
        if (config('system_settings.country_name') == 'PAK') {
            $response = $this->sendSMSPAK($text, $number);
        } else {
            $response = $this->sendSMS($text, $number);
        }

        $otp_sms->number = $number;
        $otp_sms->code = (string) $code;
        $otp_sms->save();

        return response()->json([
            'success' => true,
            'message' => 'OTP SMS sent successfully!',
            'data' => [
                'response' => $response,
                // 'otp_sms' => $otp_sms,
            ],
        ]);
    }

    private function fakeOTPSMS(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OTP SMS sent successfully!',
            'data' => [
                'response' => 'Fake OTP Sms sent.',
            ],
        ]);
    }
}
