---
title: Discovery
description: Well-known and DNS discovery support.
---

The package registers well-known redirects:

```text
/.well-known/caldav  -> /dav/
/.well-known/carddav -> /dav/
```

Clients that honor RFC 6764 can start at the application root and discover the DAV endpoint.

DNS SRV/TXT discovery is not handled by package code. Configure records at the hosting layer when you need DNS-based discovery:

```text
_caldavs._tcp
_carddavs._tcp
```

Use TXT records such as `path=/dav/` when clients need the DAV path.
