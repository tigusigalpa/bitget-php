<?php

return [
    'api_key' => env('BITGET_API_KEY', ''),
    'secret_key' => env('BITGET_SECRET_KEY', ''),
    'passphrase' => env('BITGET_PASSPHRASE', ''),
    'demo' => env('BITGET_DEMO', false),
    'base_url' => env('BITGET_BASE_URL', \Tigusigalpa\Bitget\Client::DEFAULT_BASE_URL),
    'locale' => env('BITGET_LOCALE', 'en-US'),
];
