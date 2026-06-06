---
title: Scheduling
description: Configure CalDAV scheduling and iMIP delivery.
---

Scheduling is enabled by default. When an event carries `ORGANIZER` or `ATTENDEE` properties, the server processes scheduling messages automatically.

Local attendees receive iTip `REQUEST`, `REPLY`, and `CANCEL` messages in their scheduling inboxes. External attendees are reached with iMIP email through your Laravel mailer.

Disable scheduling entirely with:

```php
'scheduling' => [
    'enabled' => false,
],
```

## External attendees

Configure the sender address with:

```dotenv
DAV_SCHEDULING_FROM="no-reply@your-app.test"
```

Override the mail transport with `dav.scheduling.mailer`. The email carries the invitation as a `text/calendar` attachment.

To customize the email, override `Bambamboole\LaravelDav\Mail\SchedulingMessageMail`.
