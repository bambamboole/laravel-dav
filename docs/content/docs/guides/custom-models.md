---
title: Custom Models
description: Extend package models for application-specific behavior.
---

The content models are swappable:

- `calendar`
- `calendar_instance`
- `calendar_object`
- `calendar_attachment`
- `calendar_subscription`
- `calendar_proxy_membership`
- `address_book`
- `card`
- `credential`
- `scheduling_object`

To customize one, subclass the package model and point the matching config key at your subclass.

```php
namespace App\Models;

use Bambamboole\LaravelDav\Models\DavCalendar;

class TeamCalendar extends DavCalendar
{
    protected function casts(): array
    {
        return [...parent::casts(), 'settings' => 'array'];
    }
}
```

```php
'models' => [
    'calendar' => \App\Models\TeamCalendar::class,
],
```

Keep the package table name and add extra columns with a normal migration against that table.

The override must extend the package model it replaces. The resolver throws when the configured class does not match the required base model.
