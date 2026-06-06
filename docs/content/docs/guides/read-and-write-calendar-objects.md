---
title: Calendar Objects
description: Work with stored iCalendar objects.
---

Calendar objects are stored as `DavCalendarObject` models and exposed to clients as iCalendar resources.

The DAV server handles client-side `PUT`, `REPORT`, sync, recurrence, and scheduling behavior. Application code can also read typed projections from stored objects:

```php
$object = DavCalendarObject::find($id);
$data = $object->toData();

$data->raw;
$data->summary;
$data->startsAt;
$data->endsAt;
```

The raw payload remains canonical. Use typed fields for application search, previews, or UI integrations, but keep `raw` for round-tripping protocol data.

## Write from PHP

Use the calendar mutation methods when application code creates or updates objects. They update the stored payload, ETag, sync token, change log, and scheduling metadata through the same path used by the DAV endpoint:

```php
use Bambamboole\LaravelDav\Dto\CalendarObjectData;

$object = $calendar->putObject(CalendarObjectData::fromArray([
    'uid' => 'event-1',
    'summary' => 'Planning',
    'startsAt' => now()->addDay(),
    'endsAt' => now()->addDay()->addHour(),
    'timezone' => 'UTC',
]));

$object->replaceWith($updatedData, expectedEtag: $object->etag);

$object->deleteDavResource(expectedEtag: $object->etag);
```

Raw iCalendar is accepted too:

```php
$object = $calendar->putObject($icalendarPayload, 'event-1.ics');
```

## Supported components

Calendar collections can allow:

- `VEVENT`
- `VTODO`
- `VJOURNAL`

Set the `components` array on the calendar to define which components clients may store in that collection.
