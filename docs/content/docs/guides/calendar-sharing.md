---
title: Calendar Sharing
description: Share calendar collections with another local owner.
---

Calendar sharing uses the CalendarServer sharing extension. Sharees are resolved from the `mailto:` address in the share request, so the target owner must expose that email through `getDavPrincipalEmail()`.

Send the share request to the owner calendar collection:

```xml
<?xml version="1.0" encoding="utf-8" ?>
<cs:share xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">
    <cs:set>
        <d:href>mailto:teammate@example.com</d:href>
        <cs:read-write />
        <cs:common-name>Team calendar</cs:common-name>
    </cs:set>
</cs:share>
```

Use `<cs:read />` for read-only access.

The share creates a `DavCalendarInstance` for the sharee. It points at the same `DavCalendar`, has its own owner-specific URI and display name, and stores the share access level.

Application code can create the same share without making a DAV request:

```php
use Bambamboole\LaravelDav\Models\DavCalendarInstance;

$calendar->shareWith(
    $teammate,
    DavCalendarInstance::AccessReadWrite,
    shareDisplayName: 'Team calendar',
);
```

Revoke access with a remove request:

```xml
<?xml version="1.0" encoding="utf-8" ?>
<cs:share xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/">
    <cs:remove>
        <d:href>mailto:teammate@example.com</d:href>
    </cs:remove>
</cs:share>
```

Revoking a share removes the sharee instance. Calendar objects remain on the original calendar.

From PHP, revoke the share through the calendar model:

```php
$calendar->unshareWith($teammate);
```
