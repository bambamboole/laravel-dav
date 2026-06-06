---
title: Client Connection
description: Connect calendar and contact clients to the DAV endpoint.
---

Point clients at the DAV base URL:

```text
https://your-app.test/dav/
```

Many clients also support service discovery. In that case, the application root is enough:

```text
https://your-app.test/
```

The package registers redirects for:

```text
/.well-known/caldav
/.well-known/carddav
```

Use the DAV username and secret from the credential you created for that client.

## Route prefix

The default route prefix is `dav`. Change it in the published config:

```php
'route' => [
    'prefix' => 'remote.php/dav',
    'middleware' => [],
],
```

The package advertises a base URI derived from the route prefix.

## DNS discovery

RFC 6764 DNS SRV/TXT discovery is a hosting concern. If you need it, publish `_caldavs._tcp` and `_carddavs._tcp` SRV records for your HTTPS host, with a TXT record such as `path=/dav/` when clients need the DAV path.
