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
foreach ($contact->emailAddresses as $emailAddress) {
    $emailAddress->value;
    $emailAddress->types;
}
```

## Write from PHP

Use the address book mutation methods when application code creates or updates contacts. They update the vCard payload, ETag, sync token, and change log through the same path used by the DAV endpoint:

```php
use Bambamboole\LaravelDav\Dto\ContactData;

$card = $addressBook->putContact(ContactData::fromArray([
    'uid' => 'contact-1',
    'formattedName' => 'Ada Lovelace',
]));

$card->replaceWith($updatedContact, expectedEtag: $card->etag);

$card->deleteDavResource(expectedEtag: $card->etag);
```

Raw vCard payloads are accepted too:

```php
$card = $addressBook->putContact($vcardPayload, 'contact-1.vcf');
```

The server advertises vCard 3.0 and 4.0. It defaults to 3.0 for broad client compatibility and negotiates other formats when clients ask for them.
