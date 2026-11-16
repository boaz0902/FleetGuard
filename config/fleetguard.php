<?php

return [
    'name' => env('FLEETGUARD_NAME', 'FleetGuard'),
    'name_default' => env('FLEETGUARD_NAME', 'FleetGuard'),
    'tagline' => env('FLEETGUARD_TAGLINE', 'A considered car rental experience in Panabo City.'),
    'tagline_default' => 'A considered car rental experience in Panabo City.',
    'phone' => env('FLEETGUARD_PHONE'),
    'email' => env('FLEETGUARD_EMAIL'),
    'address' => env('FLEETGUARD_ADDRESS'),
    'business_hours' => env('FLEETGUARD_BUSINESS_HOURS'),
    'maps_url' => env('FLEETGUARD_MAPS_URL'),
    'social' => [
        'facebook' => env('FLEETGUARD_SOCIAL_FACEBOOK'),
        'instagram' => env('FLEETGUARD_SOCIAL_INSTAGRAM'),
        'tiktok' => env('FLEETGUARD_SOCIAL_TIKTOK'),
        'x' => env('FLEETGUARD_SOCIAL_X'),
        'youtube' => env('FLEETGUARD_SOCIAL_YOUTUBE'),
    ],
];
