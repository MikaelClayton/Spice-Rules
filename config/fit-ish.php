<?php

return [
    'base_url' => env('FIT_ISH_LIONHEART_URL', 'https://api.lionheart.f45.com'),
    'token' => env('FIT_ISH_LIONHEART_TOKEN'),
    'timeout' => 20,
    'lookback_days' => 2,
    'timezone' => 'Africa/Johannesburg',
    'sync_after' => '07:00',
    'logo_disk' => 'public',
    'class_times' => [
        '0530',
        '0600',
        '0630',
        '0700',
        '0730',
        '0900',
        '0930',
        '1200',
        '1730',
        '1800',
        '1830',
    ],
];
