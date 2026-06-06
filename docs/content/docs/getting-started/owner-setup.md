---
title: Owner Setup
description: Connect DAV principals to your application owner model.
---

DAV principals map to an owner model in your application, usually `App\Models\User`.

Implement `Bambamboole\LaravelDav\Contracts\DavOwner` on that model:

```php
use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Models\Concerns\HasDavCollections;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements DavOwner
{
    use HasDavCollections;

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

`HasDavCollections` adds Eloquent-native helpers such as `createDavCalendar()`, `createDavAddressBook()`, and calendar proxy delegation methods. The trait is optional, but it is the recommended API for creating and mutating DAV resources from application code.

The package defaults to `App\Models\User`. If your owner model lives somewhere else, publish the config and update the model:

```php
'models' => [
    'owner' => App\Models\User::class,
],
```

## Principal identity

`getDavPrincipalId()` is the stable identifier used to scope credentials, calendars, address books, scheduling inboxes, and principal URLs. Keep it stable for the lifetime of the owner.
