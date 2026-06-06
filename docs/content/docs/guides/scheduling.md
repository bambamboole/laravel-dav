---
title: Scheduling
description: Configure CalDAV scheduling and iMIP delivery.
---

Scheduling is enabled by default. When an event carries `ORGANIZER` or `ATTENDEE` properties, the server processes scheduling messages automatically.

Local attendees receive iTIP `REQUEST`, `REPLY`, and `CANCEL` messages in their scheduling inboxes. External attendees are reached with iMIP email through your Laravel mailer.

Scheduling is driven by the same CalDAV writes clients already make. When an organizer creates or updates a `VEVENT`, the server compares the scheduling properties and delivers messages to affected attendees. When an organizer deletes the event, attendees receive a cancellation. When an attendee changes their `PARTSTAT`, the organizer's copy is updated and the organizer is notified.

Disable scheduling entirely with:

```php
'scheduling' => [
    'enabled' => false,
],
```

## Addresses

Local delivery depends on principal email addresses. Each owner must return the calendar user address used by clients from `getDavPrincipalEmail()`:

```php
public function getDavPrincipalEmail(): string
{
    return $this->email;
}
```

The matching calendar user address is:

```text
mailto:user@example.com
```

If an attendee address resolves to another local owner, the message is stored in that owner's scheduling inbox. If it does not resolve locally and iMIP is configured, the message is sent by email.

## Inbox and outbox

Each principal exposes scheduling collections below their calendar home:

```text
/dav/calendars/{owner}/inbox/
/dav/calendars/{owner}/outbox/
```

The inbox stores incoming scheduling objects for local attendees. The outbox handles scheduling `POST` requests such as free/busy queries.

Clients usually discover these URLs through CalDAV properties, but direct protocol tests and custom clients can target them explicitly.

## Schedule tags

Scheduling objects expose a CalDAV `schedule-tag`. The tag changes when the server updates scheduling state, such as when an attendee reply changes the organizer's copy.

Clients can send `If-Schedule-Tag-Match` on `PUT` requests to avoid overwriting server-side scheduling changes. If the provided tag does not match the stored object, the server rejects the write with a precondition failure.

## External attendees

Configure the sender address with:

```dotenv
DAV_SCHEDULING_FROM="no-reply@your-app.test"
```

Override the mail transport with `dav.scheduling.mailer`. The email carries the invitation as a `text/calendar` attachment.

To customize the email, override `Bambamboole\LaravelDav\Mail\SchedulingMessageMail`.

## Availability

Availability follows RFC 7953 and is used by free/busy responses. Store a principal's working hours as a `VAVAILABILITY` document in the `calendar-availability` property of their scheduling inbox:

```text
PROPPATCH /dav/calendars/{owner}/inbox/
```

The property storage backend must be available for this to work. It is enabled by default.

## Limitations

Scheduling currently focuses on `VEVENT` invitations. `VTODO` scheduling is not supported.

Managed attachment mutations are rejected on attendee scheduling copies. The organizer's copy remains the source of truth for managed attachment changes.

Sabre reference: [CalDAV scheduling](https://sabre.io/dav/scheduling/).
