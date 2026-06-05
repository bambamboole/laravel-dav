---
title: Contacts
description: Work with stored vCards and typed contact data.
---

Contacts are stored as `DavCard` models. Each card preserves the uploaded vCard in `card_data`.

Read a typed projection from a card:

```php
$card = DavCard::find($id);
$contact = $card->toData();

$contact->formattedName;
$contact->organization;
$contact->birthday;
```

Common multi-value fields are exposed as value objects:

```php
foreach ($contact->emails as $email) {
    $email->address;
    $email->type;
}
```

The server advertises vCard 3.0 and 4.0. It defaults to 3.0 for broad client compatibility and negotiates other formats when clients ask for them.
