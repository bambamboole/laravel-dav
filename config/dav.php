<?php

use App\Models\User;

return [
    'owner_model' => env('DAV_OWNER_MODEL', User::class),
    'owner_table' => 'users',

    'route' => [
        'prefix' => env('DAV_BASE_PREFIX', 'dav'),
        'middleware' => [],
    ],

    'base_uri' => env('DAV_BASE_URI', '/dav/'),
    'realm' => env('DAV_REALM', config('app.name', 'Laravel')),

    'principal_prefix' => 'principals',
    'calendar_prefix' => 'calendars',
    'address_book_prefix' => 'addressbooks',
    'default_calendar_uri' => 'personal',
    'default_address_book_uri' => 'personal',
];
