---
title: Raw Data and DTOs
description: Lossless storage with typed projections.
---

Calendar objects and contacts keep the original iCalendar or vCard text as the canonical payload.

Typed DTOs provide a convenient projection over common fields:

- `Bambamboole\LaravelDav\Dto\CalendarObjectData`
- `Bambamboole\LaravelDav\Dto\ContactData`

Parsed fields are nullable by design. If a source payload omits or malforms a value, the typed field is `null` while the raw payload remains intact.

Parse raw iCalendar directly:

```php
use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;

$data = app(CalendarObjectParser::class)->parse($rawICalendar);

$data->raw;
$data->summary;
$data->startsAt;
$data->isAllDay;
```

Read typed data from stored models:

```php
$calendarData = $object->toData();
$contactData = $card->toData();
```

When application code writes resources, prefer the model mutation methods over manually filling payload columns:

```php
$object = $calendar->putObject($calendarData);
$card = $addressBook->putContact($contactData);
```

Those methods keep raw payloads, ETags, sync tokens, and change records aligned with DAV endpoint writes.
