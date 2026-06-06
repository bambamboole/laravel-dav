---
title: Address Books
description: How contact collections and cards are represented.
---

Address books are stored as `DavAddressBook` models. Contacts are stored as `DavCard` models and contain the raw vCard payload.

```php
use Bambamboole\LaravelDav\Models\DavAddressBook;

DavAddressBook::create([
    'owner_id' => $user->id,
    'uri' => 'personal',
    'display_name' => 'Contacts',
]);
```

Cards are stored exactly as uploaded in `card_data`. The server advertises both vCard 3.0 and 4.0 and serves the version negotiated by the client where possible.

The typed contact DTO covers common fields across vCard versions while preserving the original raw payload for lossless round-trips.
