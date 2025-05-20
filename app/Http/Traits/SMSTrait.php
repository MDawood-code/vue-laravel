<?php

namespace App\Http\Traits;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

trait SMSTrait
{
    /** @return JsonResponse|bool|array<mixed> */
    protected function sendSMS(string $msg_body, string $to_mobile_no): JsonResponse|bool|array|null
    {
        if ($msg_body === '' || $msg_body === '0' || ($to_mobile_no === '' || $to_mobile_no === '0')) {
            return false;
        }

        // Configure Curl URL
        $url = 'https://el.cloud.unifonic.com/rest/SMS/messages?AppSid='.config('sms_notf.unifonic_app_id')
            ."&SenderID=ANY-POS&Body={$msg_body}&Recipient={$to_mobile_no}&responseType=JSON"
            .'&CorrelationID=%2522%2522&baseEncode=true&MessageType=3&statusCallback=sent&async=false';

        try {
            $response = Http::post($url);

            return $response->json();
        } catch (Exception $e) {
            echo 'Exception when calling SMSApi->smsSendPost: ', $e->getMessage(), PHP_EOL;

            return false;
        }
    }

    /** @return JsonResponse|bool|array<mixed> */
    protected function sendSMSPak(string $msg_body, string $to_mobile_no): JsonResponse|bool|array
    {

        if ($msg_body === '' || $msg_body === '0' || ($to_mobile_no === '' || $to_mobile_no === '0')) {
            return false;
        }
        $email = config('sms_notf.sms_tech_email');
        $key = config('sms_notf.sms_tech_key');
        $mask = config('sms_notf.sms_tech_mask');
        $data = [
            'email' => $email,
            'key' => $key,
            'mask' => urlencode((string) $mask),
            'to' => $to_mobile_no,
            'message' => $msg_body,
        ];
        $url = 'https://secure.h3techs.com/sms/api/send';

        try {
            $response = Http::asForm()->post($url, $data);
            if ($response->successful()) {
                return $response->json();
            }

            return false;
        } catch (Exception $e) {
            echo 'Exception when sending SMS: ', $e->getMessage(), PHP_EOL;

            return false;
        }
    }
}
