<?php

use App\Domain\Payments\Gateways\DgePayGateway;
use App\Domain\Payments\Gateways\SslCommerzGateway;

/*
|--------------------------------------------------------------------------
| Payment gateways
|--------------------------------------------------------------------------
|
| Credentials, the sandbox/live mode and the active gateway are admin
| settings (Settings → Payment gateways). Only the API addresses live here.
|
*/

return [

    'gateways' => [
        'sslcommerz' => SslCommerzGateway::class,
        'dgepay' => DgePayGateway::class,
    ],

    'sslcommerz' => [
        'sandbox' => env('SSLCOMMERZ_SANDBOX_URL', 'https://sandbox.sslcommerz.com'),
        'live' => env('SSLCOMMERZ_LIVE_URL', 'https://securepay.sslcommerz.com'),
    ],

    'dgepay' => [
        'sandbox' => env('DGEPAY_SANDBOX_URL', 'https://api-UAT.dgepay.net/dipon/v3'),
        'live' => env('DGEPAY_LIVE_URL', 'https://apiv2.dgepay.net/dipon/v3'),
    ],

    // Seconds to wait for a gateway API before giving up.
    'timeout' => 30,

];
