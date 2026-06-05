---
title: Free/Busy and Availability
description: Use free/busy queries and principal availability.
---

CalDAV free/busy queries work for local principals and scheduling flows.

Availability follows RFC 7953. A principal can publish working hours by storing a `VAVAILABILITY` document in the `calendar-availability` property of their scheduling inbox:

```text
PROPPATCH /dav/calendars/{owner}/inbox/
```

Free/busy responses mark time outside those windows as `BUSY-UNAVAILABLE`.

This lets clients distinguish explicit busy events from unavailable working-hour boundaries when they support availability.
