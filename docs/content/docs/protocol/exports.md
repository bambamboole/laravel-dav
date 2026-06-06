---
title: Exports
description: Export calendar and address book collections as single files.
---

Laravel DAV enables sabre/dav's iCalendar and vCard export plugins.

Export a calendar collection by appending `?export` to the calendar URL:

```text
GET /dav/calendars/{owner}/{calendar}/?export
```

The response is a merged `text/calendar` download containing the calendar's stored iCalendar objects.

Calendar exports accept sabre/dav's query options:

| Option | Description |
| --- | --- |
| `start` | Unix timestamp lower bound for exported events. |
| `end` | Unix timestamp upper bound for exported events. |
| `expand` | Expand recurring events. Requires `start` and `end`. |
| `accept=jcal` | Return jCal instead of iCalendar. |
| `componentType` | Filter to a component type such as `VEVENT` or `VTODO`. |

When `start`, `end`, or `expand` is used, sabre exports only `VEVENT` data for that filtered response.

Export an address book collection the same way:

```text
GET /dav/addressbooks/{owner}/{addressBook}/?export
```

The response is a `text/directory` download containing the stored vCards.

Exports use the same HTTP Basic credentials and ACL checks as normal DAV reads. They are useful for backups, clients that can subscribe to `.ics` URLs but do not speak CalDAV, and one-off address book downloads.

Sabre references: [iCalendar export](https://sabre.io/dav/ics-export-plugin/) and [vCard export](https://sabre.io/dav/vcf-export-plugin/).
