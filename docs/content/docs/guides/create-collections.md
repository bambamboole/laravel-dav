---
title: Create Collections
description: Create calendars and address books for owners.
---

Laravel DAV does not auto-create default calendars or address books. Create collections when it makes sense for your application, usually when an owner is created.

## Calendar

```php
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;

$calendar = DavCalendar::create([
    'owner_id' => $user->id,
    'components' => ['VEVENT', 'VTODO'],
]);

$calendar->instances()->create([
    'owner_id' => $user->id,
    'uri' => 'personal',
    'display_name' => 'Personal',
    'color' => '#3b82f6',
    'access' => DavCalendarInstance::AccessOwner,
]);
```

## Address book

```php
use Bambamboole\LaravelDav\Models\DavAddressBook;

DavAddressBook::create([
    'owner_id' => $user->id,
    'uri' => 'personal',
    'display_name' => 'Contacts',
]);
```

Use stable, URL-safe `uri` values. Existing clients may keep references to collection URLs after their first sync.
