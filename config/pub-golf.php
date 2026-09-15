<?php

return [

    'photo_disk' => 'public',

    'photo_directory' => 'pub-golf/drinks',

    'photo_max_edge' => 1280,

    'photo_quality' => 78,

    'photo_max_kilobytes' => 10240,

    'stale_after_hours' => 4,

    'location_reverse_url' => env('PUB_GOLF_LOCATION_REVERSE_URL', 'https://photon.komoot.io/reverse'),

    'location_search_user_agent' => sprintf(
        '%s (%s; pub-golf locations)',
        env('APP_NAME', 'Laravel'),
        rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    ),

    'location_search_connect_timeout' => 2,

    'location_search_timeout' => 4,

];
