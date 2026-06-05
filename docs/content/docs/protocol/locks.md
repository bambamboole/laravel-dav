---
title: Locks
description: WebDAV lock support.
---

The package includes a WebDAV lock backend backed by the `dav_locks` table.

Clients can use `LOCK` and `UNLOCK` requests for resources that require WebDAV locking semantics. Locks are stored independently from calendar and address book object payloads.

This is most relevant for clients that expect generic WebDAV behavior around concurrent writes.
