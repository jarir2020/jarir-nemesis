<?php
// Nemesis 4.0.0 | CORS configuration | Created: 2026-04-03
// Used by Nemesis\Http\Middleware\CorsMiddleware (constructor reads these keys).
// Wire via Kernel 'api' group or per-route: ->middleware('cors')

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    | '*' permits any origin.  For production, list specific origins:
    |   ['https://yourapp.com', 'https://admin.yourapp.com']
    */
    'origins' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed HTTP Methods
    |--------------------------------------------------------------------------
    */
    'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Request Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
        'X-CSRF-TOKEN',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allow Credentials (cookies, auth headers)
    |--------------------------------------------------------------------------
    | Set to true only when origins is NOT '*'.
    */
    'credentials' => false,

    /*
    |--------------------------------------------------------------------------
    | Preflight Cache (seconds)
    |--------------------------------------------------------------------------
    */
    'max_age' => 86400,

];
