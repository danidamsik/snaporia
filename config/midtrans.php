<?php

return [
    'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'notification_url' => env(
        'MIDTRANS_NOTIFICATION_URL',
        rtrim((string) env('APP_URL', 'http://localhost'), '/').'/payment/midtrans/notification'
    ),
];
