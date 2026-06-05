---
title: Calendar Proxy Delegation
description: Grant account-level calendar access to another local owner.
---

Calendar proxy delegation grants another local owner access to all calendars under a delegating principal. It is different from calendar sharing, which grants access to one calendar collection.

Each owner exposes two proxy principals:

```text
/dav/principals/{owner}/calendar-proxy-read/
/dav/principals/{owner}/calendar-proxy-write/
```

The examples use the default `/dav` route prefix. Replace it with your configured `dav.route.prefix` when you change that setting.

Read delegates can discover and read calendars under `/dav/calendars/{owner}/`. Write delegates can also create, update, and delete calendar objects in those calendars.

Set `DAV:group-member-set` on the proxy principal to add delegates:

```xml
<?xml version="1.0" encoding="utf-8" ?>
<d:propertyupdate xmlns:d="DAV:">
    <d:set>
        <d:prop>
            <d:group-member-set>
                <d:href>/dav/principals/42/</d:href>
            </d:group-member-set>
        </d:prop>
    </d:set>
</d:propertyupdate>
```

Send that body with `PROPPATCH` to the delegator's read or write proxy principal:

```text
PROPPATCH /dav/principals/{owner}/calendar-proxy-read/
PROPPATCH /dav/principals/{owner}/calendar-proxy-write/
```

Revoke all delegates for a proxy group by setting an empty member set:

```xml
<?xml version="1.0" encoding="utf-8" ?>
<d:propertyupdate xmlns:d="DAV:">
    <d:set>
        <d:prop>
            <d:group-member-set />
        </d:prop>
    </d:set>
</d:propertyupdate>
```

Delegation is stored in `DavCalendarProxyMembership` rows with `owner_id`, `delegate_owner_id`, and `access`. The `access` value is `read` for `calendar-proxy-read` and `write` for `calendar-proxy-write`.
