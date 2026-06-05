# Laravel DAV

CalDAV & CardDAV server for Laravel, powered by [sabre/dav](https://sabre.io/dav/), with a typed DTO API.

Expose your application's calendars and contacts to any standards-compliant client — Apple Calendar, Apple Contacts, Thunderbird, DAVx⁵, and friends — backed by Eloquent and your own owner model.

## Features

- **Full CalDAV** — events (`VEVENT`), todos (`VTODO`), and journals (`VJOURNAL`).
- **Full CardDAV** — contacts (`VCARD`) with rich, typed parsing.
- **WebDAV sync** — collection synchronization via sync tokens ([RFC 6578](https://datatracker.ietf.org/doc/html/rfc6578)).
- **Service discovery** — `/.well-known/caldav` and `/.well-known/carddav` redirects ([RFC 6764](https://datatracker.ietf.org/doc/html/rfc6764)).
- **HTTP Basic authentication** — stateless, backed by hashed credentials.
- **Owner-agnostic** — any model that implements a small contract can own collections.
- **Typed DTOs** — every calendar object and contact carries the verbatim `raw` payload plus best-effort, strongly-typed parsed fields.
- **Eloquent storage** — collections and objects are plain models you can query, extend, and relate to the rest of your app.

## Known limitations

The following are not implemented yet and are tracked for future releases:

- **RFC 6638 scheduling** — inbox/outbox, auto-schedule, free/busy queries, and iMIP invitations.
- **Calendar sharing & proxy delegation.**
- **vCard 4.0 / jCard** — contacts are parsed and stored as vCard 3.0.
- **Server-side expansion of recurring `VTODO`s** (`<C:expand>`) — clients expand recurrences themselves.

## Requirements

- PHP `^8.3`
- Laravel `^12` or `^13`
- sabre/dav `^4.7`

## Installation

```bash
composer require bambamboole/laravel-dav
```

The package auto-registers its service provider and loads its migrations automatically, so a `php artisan migrate` is all you need to create the tables.

Publishing is optional:

```bash
# Publish the config file (config/dav.php) to customize the owner model, prefixes, etc.
php artisan vendor:publish --tag=dav-config

# Publish the migrations if you want to customize the schema before migrating.
php artisan vendor:publish --tag=dav-migrations
```

## Owner setup

DAV principals (calendars and address books belong to a principal) map onto an "owner" model in your application — typically your `User`. Implement `Bambamboole\LaravelDav\Contracts\DavOwner` on it:

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

Then point `dav.owner_model` at it (defaults to `App\Models\User`), either in the published config or via the `DAV_OWNER_MODEL` environment variable:

```php
// config/dav.php
'owner_model' => App\Models\User::class,
```

## Creating credentials

DAV clients authenticate over HTTP Basic. Rather than reusing your app's login password, each client gets its own `DavCredential` — a username plus a hashed secret. Generate a random secret, store its hash, and show the plaintext to the user **once**:

```php
use Bambamboole\LaravelDav\Models\DavCredential;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$secret = Str::random(32);

DavCredential::create([
    'user_id' => $user->id,
    'name' => 'iPhone',
    'username' => 'manuel',
    'secret_hash' => Hash::make($secret),
]);

// Display $secret to the user now — it cannot be recovered later.
```

The client then authenticates with the `username` and the plaintext `$secret`. The package verifies it against `secret_hash` and records `last_used_at` on each successful request.

## Endpoints

All DAV traffic is served under the configured route prefix (`dav.route.prefix`, default `dav`):

```
https://your-app.test/dav/
```

These routes are registered **outside** the `web` middleware group: authentication is stateless HTTP Basic, so there is no session and no CSRF token to worry about. The well-known discovery URLs redirect into the prefix:

```
/.well-known/caldav  → /dav/
/.well-known/carddav → /dav/
```

Point a client at `https://your-app.test/dav/` (or just `https://your-app.test/` if it honors well-known discovery) and supply the Basic credentials created above.

## Collections

The package does **not** auto-create default calendars or address books. Your application decides when and how collections come into being — typically when an owner is created. Create them through the Eloquent models:

```php
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavAddressBook;

DavCalendar::create([
    'user_id' => $user->id,
    'uri' => 'personal',
    'display_name' => 'Personal',
    'color' => '#3b82f6',
    'components' => ['VEVENT', 'VTODO'],
]);

DavAddressBook::create([
    'user_id' => $user->id,
    'uri' => 'personal',
    'display_name' => 'Contacts',
]);
```

## The DTO API

Calendar objects and contacts are exposed as immutable, strongly-typed DTOs: `Bambamboole\LaravelDav\Dto\CalendarObjectData` and `Bambamboole\LaravelDav\Dto\ContactData`. Each DTO carries the verbatim `raw` payload (the canonical iCalendar / vCard text, which always round-trips losslessly) alongside best-effort parsed fields. Parsed fields are nullable by design — if the source omits or malforms a value, the typed field is simply `null` while `raw` stays intact.

Parse a raw payload directly:

```php
use Bambamboole\LaravelDav\Parsing\CalendarObjectParser;

$data = app(CalendarObjectParser::class)->parse($rawICalendar);

$data->raw;       // the original iCalendar string (canonical)
$data->summary;   // ?string
$data->startsAt;  // ?Carbon\CarbonImmutable
$data->isAllDay;  // bool
```

Or get a DTO straight off a stored model:

```php
$object = DavCalendarObject::find($id);
$data = $object->toData(); // CalendarObjectData

$card = DavCard::find($id);
$contact = $card->toData(); // ContactData
```

`ContactData` decomposes a vCard into typed value objects under `Bambamboole\LaravelDav\Dto\Contact\*` — for example `ContactEmailAddress`, `ContactPhoneNumber`, `ContactPostalAddress`, `ContactUrl`, `ContactDate`, and `ContactSocialProfile`:

```php
foreach ($contact->emails as $email) {
    $email->address; // string
    $email->type;    // ?string (e.g. "work", "home")
}

$contact->formattedName; // ?string
$contact->organization;  // ?string
$contact->birthday;      // ?ContactDate
```

## Reacting to changes

Every collection mutation (a created, updated, or deleted object) fires `Bambamboole\LaravelDav\Events\DavCollectionChanged`. It is a plain event you can listen for — handy for pushing live updates to a UI:

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

The event exposes:

| Property        | Type           | Description                                             |
| --------------- | -------------- | ------------------------------------------------------- |
| `$ownerId`      | `int\|string`  | The owner the collection belongs to.                    |
| `$type`         | `string`       | `calendar` or `addressbook`.                            |
| `$collectionId` | `int`          | The collection's primary key.                           |
| `$resourceUri`  | `?string`      | The affected object URI (null for collection-level ops).|
| `$operation`    | `string`       | `created`, `updated`, or `deleted`.                     |
| `$syncToken`    | `int`          | The collection's new sync token.                        |

## Configuration

The published `config/dav.php` exposes:

| Key                         | Default                | Description                                              |
| --------------------------- | ---------------------- | ------------------------------------------------------- |
| `owner_model`               | `App\Models\User`      | The model that owns principals/collections.             |
| `owner_table`               | `users`                | The owner model's table.                                |
| `route.prefix`              | `dav`                  | URL prefix the DAV server is served under.              |
| `route.middleware`          | `[]`                   | Extra middleware applied to the DAV routes.             |
| `base_uri`                  | `/dav/`                | The base URI advertised to clients.                     |
| `realm`                     | app name               | HTTP Basic authentication realm.                        |
| `principal_prefix`          | `principals`           | Path segment for principals.                            |
| `calendar_prefix`           | `calendars`            | Path segment for calendar collections.                  |
| `address_book_prefix`       | `addressbooks`         | Path segment for address book collections.              |
| `default_calendar_uri`      | `personal`             | Conventional URI for an owner's primary calendar.       |
| `default_address_book_uri`  | `personal`             | Conventional URI for an owner's primary address book.   |
| `models.*`                  | package models         | Override the content models (see below).                |

## Customizing the models

The content models — `calendar`, `calendar_object`, `address_book`, `card`, and
`credential` — are swappable. To customize one, **subclass the package model** and
point the matching `config('dav.models.*')` key at your subclass. Because the
package type-hints the concrete model, your subclass satisfies every internal call
while letting you add columns (via your own migration), casts, relations, scopes,
or traits such as tenancy.

Keep the package table name (subclasses inherit it automatically) and add your
extra columns with a regular migration against that table.

```php
namespace App\Models;

use Bambamboole\LaravelDav\Models\DavCalendar;
use App\Database\Factories\TeamCalendarFactory;

class TeamCalendar extends DavCalendar
{
    use BelongsToTeam;

    protected function casts(): array
    {
        return [...parent::casts(), 'settings' => 'array'];
    }

    // Only needed if you want TeamCalendar::factory() to build your subclass;
    // the package factory builds the default DavCalendar.
    protected static function newFactory(): TeamCalendarFactory
    {
        return TeamCalendarFactory::new();
    }
}
```

```php
// config/dav.php
'models' => [
    'calendar' => \App\Models\TeamCalendar::class,
],
```

The override must extend the package model it replaces; the resolver throws if it
does not. The same pattern applies to every content model key.

## Testing

```bash
composer test
```

### CalDAV server compatibility harness

`tests/Integration/CaldavServerTesterTest.php` boots the DAV server as a real
HTTP process (via `orchestra/testbench serve`) and runs the external
[`caldav-server-tester`](https://github.com/python-caldav/caldav-server-tester)
against it. It parses the JSON the tester emits into a typed
`CaldavTesterResult` DTO and asserts the current status quo feature by feature
(e.g. `expect($result->support('scheduling'))->toBe(SupportLevel::Unsupported)`).
Many features are known to be unsupported today; the per-feature expectations
record that reality so the suite stays green. As the server improves, update the
matching expectation (e.g. from `Unsupported` to `Full`) so the diff documents
the progress.

This test is part of the default `composer test` run, so the tester binary must
be installed wherever the suite runs:

```bash
uv tool install --with vobject caldav-server-tester
```

The `--with vobject` extra is required: the tester's `save-load.event.timezone`
check reads events back through the optional `vobject` library and grades the
feature `broken` when it is missing, regardless of server behaviour.

If the binary lives outside `~/.local/bin` and your `PATH`, point the test at it
with `CALDAV_SERVER_TESTER_BIN=/path/to/caldav-server-tester`.

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
