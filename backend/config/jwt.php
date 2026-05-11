<?php

use Illuminate\Support\Str;

return [
    'secret' => env('JWT_SECRET', ''),
    'access_ttl' => env('JWT_TTL', 3600),
    'refresh_ttl' => env('JWT_REFRESH_TTL', 604800),
];
