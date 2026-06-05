---
title: Principals and Owners
description: Understand how DAV principals map to application models.
---

A DAV principal represents the authenticated person or account that owns calendars and address books.

Laravel DAV does not require a specific user model. Any model can be an owner when it implements `Bambamboole\LaravelDav\Contracts\DavOwner`.

The owner contract supplies:

| Method | Purpose |
| --- | --- |
| `getDavPrincipalId()` | Stable owner identifier for scoping DAV data. |
| `getDavPrincipalDisplayName()` | Human-readable name exposed through principal properties. |
| `getDavPrincipalEmail()` | Email address used for scheduling and principal discovery. |

The default principal path is:

```text
/dav/principals/{owner}
```

Calendar and address book collections are grouped under the same owner identity.

Each owner principal also exposes CalDAV proxy groups:

```text
/dav/principals/{owner}/calendar-proxy-read/
/dav/principals/{owner}/calendar-proxy-write/
```

Those proxy principals store account-level calendar delegates. Use calendar sharing when access should apply to one calendar collection, and proxy delegation when access should apply to the owner's calendars as a principal-level delegation.
