---
title: Calendar Subscriptions
description: Expose remote iCalendar feeds as subscribed calendars.
---

Calendar subscriptions use the CalendarServer subscription extension implemented by sabre/dav. They let a client add a remote iCalendar feed to an owner's calendar home without turning that feed into a local editable calendar.

Subscriptions live beside normal calendars under the owner's calendar home:

```text
/dav/calendars/{owner}/holidays/
```

Create one with `MKCOL` and the `subscribed` resource type:

```xml
<?xml version="1.0" encoding="utf-8" ?>
<d:mkcol xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:ical="http://apple.com/ns/ical/">
    <d:set>
        <d:prop>
            <d:resourcetype>
                <d:collection />
                <cs:subscribed />
            </d:resourcetype>
            <cs:source>
                <d:href>https://example.com/holidays.ics</d:href>
            </cs:source>
            <d:displayname>Holidays</d:displayname>
            <ical:calendar-color>#B8255FFF</ical:calendar-color>
            <ical:refreshrate>P1D</ical:refreshrate>
            <ical:calendar-order>10</ical:calendar-order>
            <cs:subscribed-strip-todos />
            <cs:subscribed-strip-alarms />
            <cs:subscribed-strip-attachments />
        </d:prop>
    </d:set>
</d:mkcol>
```

The `source` property is required. The other properties are optional client metadata:

| Property | Stored field |
| --- | --- |
| `{http://calendarserver.org/ns/}source` | `source` |
| `{DAV:}displayname` | `display_name` |
| `{http://apple.com/ns/ical/}calendar-color` | `color` |
| `{http://apple.com/ns/ical/}refreshrate` | `refresh_rate` |
| `{http://apple.com/ns/ical/}calendar-order` | `order` |
| `{http://calendarserver.org/ns/}subscribed-strip-todos` | `strip_todos` |
| `{http://calendarserver.org/ns/}subscribed-strip-alarms` | `strip_alarms` |
| `{http://calendarserver.org/ns/}subscribed-strip-attachments` | `strip_attachments` |

Update subscription metadata with `PROPPATCH` against the subscription URL. Remove the subscription with `DELETE`; this deletes only the `DavCalendarSubscription` row and leaves normal calendars untouched.

Application code can create subscriptions directly through the model:

```php
use Bambamboole\LaravelDav\Models\DavCalendarSubscription;

DavCalendarSubscription::create([
    'owner_id' => $owner->getKey(),
    'uri' => 'holidays',
    'source' => 'https://example.com/holidays.ics',
    'display_name' => 'Holidays',
    'color' => '#B8255FFF',
    'refresh_rate' => 'P1D',
    'order' => 10,
    'strip_todos' => true,
    'strip_alarms' => true,
    'strip_attachments' => true,
    'last_modified_at' => now(),
]);
```

The server stores subscription metadata and advertises it to clients. It does not fetch, cache, or rewrite the remote feed contents.

Sabre reference: [calendar subscriptions](https://sabre.io/dav/upgrade/1.8-to-2.0/).
