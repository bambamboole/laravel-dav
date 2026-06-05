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

## Supported components

Calendar collections can allow:

- `VEVENT`
- `VTODO`
- `VJOURNAL`

Set the `components` array on the calendar to define which components clients may store in that collection.
