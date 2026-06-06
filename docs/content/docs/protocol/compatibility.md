---
title: Compatibility
description: Current compatibility checks.
---

Laravel DAV 0.2.0 is a CalDAV/CardDAV package built on sabre/dav. It intentionally exposes the WebDAV surface needed for calendar and contact clients, not a generic WebDAV file server.

## Standards

| Area | Status | Notes |
| --- | --- | --- |
| WebDAV, RFC 4918 | Partial | DAV methods are routed through sabre/dav for calendar, address book, principal, scheduling, subscription, lock, and property resources. Arbitrary file trees are out of scope. |
| HTTP Basic auth, RFC 7617 | Supported | Backed by `DavCredential` records. Digest auth is not supported. |
| ACL, RFC 3744 | Supported | Used for principals, owner access, calendar sharing, proxy delegation, and read/write enforcement. |
| Current Principal, RFC 5397 | Supported | Exposed through the ACL plugin for authenticated DAV clients. |
| CalDAV, RFC 4791 | Supported | Calendar collections, `VEVENT`, `VTODO`, `VJOURNAL`, calendar-query, multiget, free/busy, and calendar properties. |
| iCalendar, RFC 5545 | Supported | Parsed and serialized through sabre/vobject. |
| iTIP, RFC 5546 | Partial | Used for `VEVENT` scheduling flows. `VTODO` scheduling is not supported. |
| iMIP, RFC 6047 | Supported | External scheduling messages are sent through Laravel mail when scheduling is enabled. |
| CardDAV, RFC 6352 | Supported | Address book collections, vCard resources, addressbook-query, multiget, and address book properties. |
| vCard 3.0 and 4.0 | Supported | The server advertises both and defaults to vCard 3.0 for compatibility. |
| WebDAV Sync, RFC 6578 | Supported | Calendar and address book changes advance sync tokens. |
| CalDAV Scheduling, RFC 6638 | Partial | Local delivery, replies, cancellations, free/busy, iMIP, and schedule tags are supported for events. |
| Service discovery, RFC 6764 | Partial | The package registers `.well-known/caldav` and `.well-known/carddav` redirects. DNS SRV/TXT records are a hosting concern. |
| jCard, RFC 7095 | Partial | Available through sabre's CardDAV conversion path when clients negotiate it. |
| jCal, RFC 7265 | Partial | Calendar exports can request jCal with `accept=jcal`. |
| Calendar availability, RFC 7953 | Supported | `VAVAILABILITY` is stored on the scheduling inbox through property storage. |
| Managed attachments, RFC 8607 | Partial | Add, update, remove, and download are supported. Per-recurrence attachment operations are not implemented. |
| CalendarServer sharing | Supported | Owners can share calendars read-only or read-write with local owners. |
| CalendarServer proxy delegation | Supported | `calendar-proxy-read` and `calendar-proxy-write` principals are exposed for each owner. |
| Calendar subscriptions | Supported | Server-managed subscription metadata is exposed through the CalendarServer subscription extension. |

## Out of scope

These sabre/dav features are not part of Laravel DAV 0.2.0:

- Generic WebDAV file hosting.
- WebDAV browser UI.
- DAV mounting documents.
- Quota properties.
- WebDAV SEARCH.
- Delta-V versioning.
- Ordered collections.
- WebDAV BIND.
- CardDAV directory and me-card resources.

## Test harness

The test suite includes a CalDAV server compatibility harness that boots the DAV server as a real HTTP process and runs `caldav-server-tester` against it.

Install the tester where the suite runs:

```bash
uv tool install --with vobject caldav-server-tester
```

If the binary is not on `PATH`, set:

```bash
CALDAV_SERVER_TESTER_BIN=/path/to/caldav-server-tester
```

The compatibility test records current support status feature by feature. As server support improves, update the matching expectations so the diff documents the protocol progress.

Calendar sharing and calendar proxy delegation are covered by feature tests. Keep those expectations aligned with the compatibility harness as client-facing protocol support changes.

Sabre reference: [standards support](https://sabre.io/dav/standards-support/).
