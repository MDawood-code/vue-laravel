<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Http\Traits\SMSTrait;
use App\Models\OTPSMS;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * @group Customer
 *
 * @subgroup Forgot Password
 *
 * @subgroupDescription APIs for managing Forgot Password
 */
class ForgotPasswordController extends Controller
{
    use SMSTrait;

    /**
     * Send forgot password request.
     *
     * @bodyParam phone string required The phone of the user. Example: '9611234567890'.
     *
     * @unauthenticated
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'min:12', 'max:16'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['success' => false, 'message' => $validator->errors()], 422);
        }

        $verify = User::where('phone', $request->phone)->exists();

        if ($verify) {
            return $this->sendOTPSMS($request->phone);
        } else {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Account with this phone number does not exist.',
                ],
                400
            );
        }
    }

    /**
     * Verify pin.
     *
     * @bodyParam phone string required The phone of the user. Example: '9611234567890'.
     * @bodyParam otp string required The otp received on phone. Example: '9087'.
     *
     * @unauthenticated
     */
    public function verifyPin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => ['required', 'string', 'min:12', 'max:16'],
            'otp' => ['required'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['success' => false, 'message' => $validator->errors()], 422);
        }

        $otp_sms = OTPSMS::where([
            'number' => $request->phone,
            'code' => $request->otp,
        ])->first();
        if (! $otp_sms) {
            return response()->json([
                'success' => false,
                'message' => 'OTP validation Failed.',
                'data' => [
                    'errors' => $validator->messages()->toArray(),
                ],
            ], 400);
        }

        // $difference = Carbon::now()->diffInSeconds($otp_sms->first()->updated_at);
        // if ($difference > 3600) {
        //     return new JsonResponse(['success' => false, 'message' => 'Token Expired'], 400);
        // }

        $token = md5(random_int(1, 10).microtime());
        $otp_sms->token = $token;
        $otp_sms->save();

        return new JsonResponse(
            [
                'success' => true,
                'token' => $token,
                'message' => 'You can now reset your password',
            ],
            200
        );
    }

    /**
     * Reset Password.
     *
     * @bodyParam phone string required The phone of the user. Example: '9611234567890'.
     * @bodyParam token string required The token received in verifyPin request. Example: 'dkfjdkfjk232'.
     * @bodyParam password string required The new password user wants to set. Example: 'abcd1234'.
     *
     * @unauthenticated
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'min:4'],
            'phone' => ['required', 'string', 'min:12', 'max:16'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['success' => false, 'message' => $validator->errors()], 422);
        }

        $otp_sms = OTPSMS::where([
            'number' => $request->phone,
            'token' => $request->token,
        ])->first();
        if (! $otp_sms) {
            return response()->json([
                'success' => false,
                'message' => 'OTP validation Failed.',
                'data' => [
                    'errors' => $validator->messages()->toArray(),
                ],
            ], 400);
        }

        $user = User::where('phone', $request->phone)->first();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $access_token = $user->createToken('authToken')->accessToken;

        return response()->json([
            'success' => true,
            'message' => 'Password reset Successful.',
            'data' => [
                'user' => new UserResource($user, $access_token, true),
            ],
        ], 201);
    }

    private function sendOTPSMS(string $phone): JsonResponse
    {
        $number = $phone;
        $code = random_int(100000, 999999);
        $text = urlencode('OTP Code for any pos to reset password is : '.$code);

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

        $response = $this->sendSMS($text, $number);

        $otp_sms->number = $number;
        $otp_sms->code = (string) $code;
        $otp_sms->save();

        return response()->json([
            'success' => true,
            'message' => 'OTP SMS sent successfully!',
            'data' => [
                'response' => $response,
                'otp_sms' => $otp_sms,
            ],
        ]);
    }
}
