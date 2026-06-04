<?php

use App\Models\User;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Models\DavCredential;

return [
    'owner_model' => env('DAV_OWNER_MODEL', User::class),
    'owner_table' => 'users',

    'models' => [
        'calendar' => DavCalendar::class,
        'calendar_object' => DavCalendarObject::class,
        'address_book' => DavAddressBook::class,
        'card' => DavCard::class,
        'credential' => DavCredential::class,
    ],

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
