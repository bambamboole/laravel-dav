---
title: CalDAV
description: Calendar protocol support.
---

CalDAV support includes calendar collections, event storage, todo storage, journal storage, recurrence-aware queries, free/busy queries, scheduling, schedule tags, availability, calendar sharing, and calendar proxy delegation.

Supported iCalendar components:

- `VEVENT`
- `VTODO`
- `VJOURNAL`

Scheduling implements local iTip delivery and optional iMIP email delivery for external attendees.

Availability support follows RFC 7953 with `VAVAILABILITY` stored on the scheduling inbox.

Calendar sharing uses the CalendarServer sharing extension. Owners can grant read-only or read-write access to another local owner identified by their principal email address.

Calendar proxy delegation exposes the `calendar-proxy-read` and `calendar-proxy-write` principals under each owner principal. Read proxy delegates can discover and read the delegator's calendars. Write proxy delegates can also create, update, and delete calendar objects.
