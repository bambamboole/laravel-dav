---
title: Owner Setup
description: Connect DAV principals to your application owner model.
---

DAV principals map to an owner model in your application, usually `App\Models\User`.

Implement `Bambamboole\LaravelDav\Contracts\DavOwner` on that model:

```php
use Bambamboole\LaravelDav\Contracts\DavOwner;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements DavOwner
{
    public function getDavPrincipalId(): string|int
    {
        return $this->getKey();
    }

    public function getDavPrincipalDisplayName(): string
    {
        return $this->name;
    }

    public function getDavPrincipalEmail(): ?string
    {
        return $this->email;
    }
}
```

The package defaults to `App\Models\User`. If your owner model lives somewhere else, publish the config and update the model:

```php
'models' => [
    'owner' => App\Models\User::class,
],
```

## Principal identity

`getDavPrincipalId()` is the stable identifier used to scope credentials, calendars, address books, scheduling inboxes, and principal URLs. Keep it stable for the lifetime of the owner.
