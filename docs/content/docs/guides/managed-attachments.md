---
title: Managed Attachments
description: Store CalDAV ATTACH files through Laravel's filesystem.
---

Managed attachments follow RFC 8607. Clients add, update, and remove calendar attachments by sending `POST` requests to the calendar object resource with one of these actions:

- `attachment-add`
- `attachment-update`
- `attachment-remove`

The server stores the uploaded bytes on the configured Laravel filesystem disk, creates a server-generated managed ID, and rewrites the calendar object's `ATTACH` property with:

- `MANAGED-ID`
- `FMTTYPE`
- `FILENAME`
- `SIZE`

Successful add and update requests return the managed ID in the `Cal-Managed-ID` response header.

## Storage

Configure the disk and base path in `config/dav.php`:

```php
'attachments' => [
    'disk' => env('DAV_ATTACHMENTS_DISK', env('FILESYSTEM_DISK', 'local')),
    'path' => env('DAV_ATTACHMENTS_PATH', 'dav-attachments'),
],
```

Attachment files are served from DAV-managed URLs. Downloads use the same read access as the owning calendar object. Direct `PUT` and `DELETE` requests to attachment URLs are rejected; clients must mutate attachments through the owning calendar object.

## Scheduling restrictions

Attendees cannot add, update, or remove managed attachments on scheduled event copies. The organizer's copy can manage attachments.

## Limitations

Per-recurrence managed attachment operations are not implemented yet.
