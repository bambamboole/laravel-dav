<?php

use App\Models\User;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarAttachment;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavCalendarProxyMembership;
use Bambamboole\LaravelDav\Models\DavCalendarSubscription;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Models\DavCredential;
use Bambamboole\LaravelDav\Models\DavSchedulingObject;

return [
    'models' => [
        'owner' => User::class,
        'calendar' => DavCalendar::class,
        'calendar_attachment' => DavCalendarAttachment::class,
        'calendar_instance' => DavCalendarInstance::class,
        'calendar_object' => DavCalendarObject::class,
        'calendar_proxy_membership' => DavCalendarProxyMembership::class,
        'calendar_subscription' => DavCalendarSubscription::class,
        'address_book' => DavAddressBook::class,
        'card' => DavCard::class,
        'credential' => DavCredential::class,
        'scheduling_object' => DavSchedulingObject::class,
    ],

    'route' => [
        'prefix' => 'dav',
        'middleware' => [],
    ],

    'realm' => env('DAV_REALM', config('app.name', 'Laravel')),

    'caldav' => [
        'enabled' => env('DAV_CALDAV_ENABLED', true),
    ],

    'carddav' => [
        'enabled' => env('DAV_CARDDAV_ENABLED', true),
    ],

    'principal_prefix' => 'principals',
    'calendar_prefix' => 'calendars',

    'scheduling' => [
        'enabled' => true,
        'mailer' => env('MAIL_MAILER'),
        'from' => env('DAV_SCHEDULING_FROM', env('MAIL_FROM_ADDRESS', 'noreply@laravel-dav.example')),
    ],

    'attachments' => [
        'disk' => env('DAV_ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
        'path' => env('DAV_ATTACHMENTS_PATH', 'dav-attachments'),
    ],
];
