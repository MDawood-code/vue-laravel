<?php

namespace App\UtilityClasses;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\ResponseInterface;

class PaymentMethod
{
    /** @var Collection<string, mixed> */
    private readonly Collection $formParams;

    private readonly Client $guzzle_client;

    public function __construct(int $paymentBrand)
    {
        $paymentMode = config('payment.mode');
        $this->formParams = collect();
        if ($paymentMode === 'TEST') {
            // Test Scenario
            if ($paymentBrand === PAYMENT_BRAND_MADA) {
                $this->formParams->put('entityId', config('payment.test_mada_entity_id'));
            } elseif (in_array($paymentBrand, [PAYMENT_BRAND_VISA, PAYMENT_BRAND_MASTER])) {
                $this->formParams->put('entityId', config('payment.test_visa_master_entity_id'));
                $this->formParams->put('testMode', 'EXTERNAL');
            }
            $apiBaseUrl = config('payment.test_base_url');
            $apiToken = config('payment.test_api_token');
        } else {
            // Live Scenario
            if ($paymentBrand === PAYMENT_BRAND_MADA) {
                $this->formParams->put('entityId', config('payment.live_mada_entity_id'));
            } elseif (in_array($paymentBrand, [PAYMENT_BRAND_VISA, PAYMENT_BRAND_MASTER])) {
                $this->formParams->put('entityId', config('payment.live_visa_master_entity_id'));
            }
            $apiBaseUrl = config('payment.live_base_url');
            $apiToken = config('payment.live_api_token');
        }

        $this->formParams->put('currency', config('payment.currency'));
        $this->formParams->put('paymentType', config('payment.type'));

        $this->guzzle_client = new Client([
            'base_uri' => $apiBaseUrl,
            'headers' => [
                'Authorization' => 'Bearer '.$apiToken,
                'Accept' => 'application/json',
            ],
        ]);
    }

    /** @param  array<mixed>  $data */
    public function checkout(array $data): ResponseInterface
    {
        $shopperResultUrl = config('payment.mode') === 'TEST' ? config('payment.frontend_dev_base_url') : config('payment.frontend_prod_base_url');
        $shopperResultUrl .= 'payments';

        $this->formParams->put('amount', $data['amount']);
        $this->formParams->put('merchantTransactionId', $data['merchantTransactionId']);
        $this->formParams->put('customer.email', $data['customerEmail']);
        $this->formParams->put('billing.street1', $data['billingAddress']);
        $this->formParams->put('billing.city', $data['billingCity']);
        $this->formParams->put('billing.state', $data['billingState']);
        $this->formParams->put('billing.country', $data['billingCountry']);
        $this->formParams->put('billing.postcode', $data['billingPostCode']);
        $this->formParams->put('customer.givenName', $data['billingFirstName']);
        $this->formParams->put('customer.surname', $data['billingLastName']);
        $this->formParams->put('shopperResultUrl', $shopperResultUrl);

        try {
            return $this->guzzle_client->request('POST',
                'checkouts',
                ['form_params' => $this->formParams->toArray()]
            );
        } catch (ClientException $e) {
            $req = Message::toString($e->getRequest());
            $res = Message::toString($e->getResponse());
            Log::debug('Payment Checkout Error: Request');
            Log::debug($req);
            Log::debug('Payment Checkout Error: Request');
            Log::debug($res);
            throw new Exception('Error Processing Payment Request', 1, $e);
        }
    }

    public function verify(string $checkoutId): ResponseInterface
    {
        $url = 'checkouts/'.$checkoutId.'/payment?entityId='.$this->formParams->get('entityId');

        return $this->guzzle_client->request('GET', $url);
    }
}
