---
title: Sync Tokens
description: How collection changes are tracked for clients.
---

WebDAV sync lets clients ask for changes since a previous token instead of refetching an entire collection.

Laravel DAV records collection mutations and advances the collection sync token when resources are created, updated, or deleted.

The package emits `Bambamboole\LaravelDav\Events\DavCollectionChanged` for collection mutations. The event includes:

| Property | Description |
| --- | --- |
| `$ownerId` | Owner identifier. |
| `$type` | `calendar` or `addressbook`. |
| `$collectionId` | Collection primary key. |
| `$resourceUri` | Affected object URI, or `null` for collection-level changes. |
| `$operation` | `created`, `updated`, or `deleted`. |
| `$syncToken` | New collection sync token. |

Clients use these tokens through RFC 6578 sync reports.
