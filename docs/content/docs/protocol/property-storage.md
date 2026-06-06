---
title: Property Storage
description: Store WebDAV dead properties through PROPPATCH.
---

Laravel DAV enables sabre/dav's property storage plugin with the `dav_properties` table. This lets clients store WebDAV properties that are not handled by a calendar, address book, subscription, or other dedicated backend.

Clients write stored properties with `PROPPATCH` and read them back with `PROPFIND`:

```xml
<?xml version="1.0" encoding="utf-8" ?>
<d:propertyupdate xmlns:d="DAV:" xmlns:x="https://example.com/ns/">
    <d:set>
        <d:prop>
            <x:pinned>1</x:pinned>
        </d:prop>
    </d:set>
</d:propertyupdate>
```

Properties are keyed by DAV path and Clark-notation property name. Deleting a DAV resource removes stored properties for that path and child paths. Moving a DAV resource moves the stored properties to the new path.

The backend stores scalar values as strings, XML properties as XML, and complex values as serialized PHP values when sabre hands them to the backend. Use dedicated package fields for application-owned data when the property is part of the Laravel DAV domain model.

## Availability

Calendar availability is stored through property storage on the scheduling inbox:

```text
PROPPATCH /dav/calendars/{owner}/inbox/
```

The supported property is:

```text
{urn:ietf:params:xml:ns:caldav}calendar-availability
```

Store a `VAVAILABILITY` payload there when a principal should publish working hours for free/busy generation.

## Access

Sabre applies ACL checks before property reads and writes. A client can only read or mutate stored properties on paths where the authenticated DAV principal has the required privileges.

Sabre reference: [property storage](https://sabre.io/dav/property-storage/).
