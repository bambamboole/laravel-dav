---
title: WebDAV
description: WebDAV behavior supported by the server.
---

Laravel DAV serves DAV traffic through sabre/dav under the configured route prefix.

The route accepts standard DAV methods including:

```text
GET
HEAD
POST
PUT
PATCH
DELETE
OPTIONS
PROPFIND
PROPPATCH
MKCOL
MKCALENDAR
COPY
MOVE
LOCK
UNLOCK
REPORT
ACL
```

Collection synchronization is supported through RFC 6578 sync tokens.

The server routes are registered outside Laravel’s `web` middleware group. Authentication is stateless HTTP Basic, so DAV requests do not use sessions or CSRF tokens.
