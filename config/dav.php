<?php

use App\Models\User;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Models\DavChange;
use Bambamboole\LaravelDav\Models\DavCredential;
use Bambamboole\LaravelDav\Models\DavLock;
use Bambamboole\LaravelDav\Models\DavProperty;
use Bambamboole\LaravelDav\Storage\EloquentAddressBookStore;
use Bambamboole\LaravelDav\Storage\EloquentCalendarStore;
use Bambamboole\LaravelDav\Storage\EloquentCredentialRepository;
use Bambamboole\LaravelDav\Storage\EloquentPrincipalRepository;

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

    'stores' => [
        'principal' => EloquentPrincipalRepository::class,
        'credential' => EloquentCredentialRepository::class,
        'calendar' => EloquentCalendarStore::class,
        'address_book' => EloquentAddressBookStore::class,
    ],

    'models' => [
        'calendar' => DavCalendar::class,
        'calendar_object' => DavCalendarObject::class,
        'address_book' => DavAddressBook::class,
        'card' => DavCard::class,
        'change' => DavChange::class,
        'credential' => DavCredential::class,
        'lock' => DavLock::class,
        'property' => DavProperty::class,
    ],
];
