---
title: Calendars
description: How calendar collections and objects are represented.
---

Calendars are stored as `DavCalendar` models. Each visible collection is represented by a `DavCalendarInstance`, which carries the owner-specific URI, display name, color, and access level. Calendar objects are stored as `DavCalendarObject` models and contain the raw iCalendar payload.

The package supports:

- `VEVENT`
- `VTODO`
- `VJOURNAL`

Each calendar has a URI, display name, color, supported components, and sync token. The URI becomes part of the client-visible collection path.

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

The package does not auto-create calendars. Your application decides when default or domain-specific calendars should exist.
