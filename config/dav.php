<?php

return [
    'owner_model' => env('DAV_OWNER_MODEL', \App\Models\User::class),
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

    'stores' => [
        'principal' => \Bambamboole\LaravelDav\Storage\EloquentPrincipalRepository::class,
        'credential' => \Bambamboole\LaravelDav\Storage\EloquentCredentialRepository::class,
        'calendar' => \Bambamboole\LaravelDav\Storage\EloquentCalendarStore::class,
        'address_book' => \Bambamboole\LaravelDav\Storage\EloquentAddressBookStore::class,
    ],

    'models' => [
        'calendar' => \Bambamboole\LaravelDav\Models\DavCalendar::class,
        'calendar_object' => \Bambamboole\LaravelDav\Models\DavCalendarObject::class,
        'address_book' => \Bambamboole\LaravelDav\Models\DavAddressBook::class,
        'card' => \Bambamboole\LaravelDav\Models\DavCard::class,
        'change' => \Bambamboole\LaravelDav\Models\DavChange::class,
        'credential' => \Bambamboole\LaravelDav\Models\DavCredential::class,
        'lock' => \Bambamboole\LaravelDav\Models\DavLock::class,
        'property' => \Bambamboole\LaravelDav\Models\DavProperty::class,
    ],
];
