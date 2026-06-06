---
title: Create Collections
description: Create calendars and address books for owners.
---

Laravel DAV does not auto-create default calendars or address books. Create collections when it makes sense for your application, usually when an owner is created.

## Calendar

```php
$calendar = $user->createDavCalendar([
    'uri' => 'personal',
    'display_name' => 'Personal',
    'color' => '#3b82f6',
    'components' => ['VEVENT', 'VTODO'],
]);
```

## Address book

```php
$addressBook = $user->createDavAddressBook([
    'uri' => 'personal',
    'display_name' => 'Contacts',
]);
```

Use stable, URL-safe `uri` values. Existing clients may keep references to collection URLs after their first sync.

The owner helpers require the owner model to use `Bambamboole\LaravelDav\Models\Concerns\HasDavCollections`. You can still use the models directly, but the helpers create the required owner calendar instance and keep the collection shape consistent.
