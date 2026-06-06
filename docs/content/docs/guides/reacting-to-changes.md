---
title: Reacting to Changes
description: Listen for collection mutations.
---

Every collection mutation fires `Bambamboole\LaravelDav\Events\DavCollectionChanged`.

Use the event to update application search indexes, notify users, or push live UI updates:

```php
use Bambamboole\LaravelDav\Events\DavCollectionChanged;
use Illuminate\Broadcasting\PrivateChannel;

class BroadcastDavChange
{
    public function handle(DavCollectionChanged $event): void
    {
        broadcast(new SomeBroadcastEvent(
            new PrivateChannel("dav.{$event->ownerId}"),
        ));
    }
}
```

The event includes the owner, collection type, collection ID, affected resource URI, operation, and new sync token.
