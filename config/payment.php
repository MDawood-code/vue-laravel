<?php

return [
    'mode' => env('PAYMENT_MODE'),
    'test_mada_entity_id' => env('PAYMENT_TEST_MADA_ENTITY_ID'),
    'test_visa_master_entity_id' => env('PAYMENT_TEST_VISA_MASTER_ENTITY_ID'),
    'test_base_url' => env('PAYMENT_TEST_BASE_URL'),
    'test_api_token' => env('PAYMENT_TEST_API_TOKEN'),
    'live_mada_entity_id' => env('PAYMENT_LIVE_MADA_ENTITY_ID'),
    'live_visa_master_entity_id' => env('PAYMENT_LIVE_VISA_MASTER_ENTITY_ID'),
    'live_base_url' => env('PAYMENT_LIVE_BASE_URL'),
    'live_api_token' => env('PAYMENT_LIVE_API_TOKEN'),
    'currency' => env('PAYMENT_CURRENCY'),
    'type' => env('PAYMENT_TYPE'),
    'frontend_dev_base_url' => env('FRONTEND_DEV_BASE_URL'),
    'frontend_prod_base_url' => env('FRONTEND_PROD_BASE_URL'),
];
