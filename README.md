<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/assets/logo-dark.svg" />
    <img alt="LaravelDAV" src="docs/assets/logo.svg" width="300" />
  </picture>
</p>

# Laravel DAV

CalDAV & CardDAV server for Laravel, powered by [sabre/dav](https://sabre.io/dav/), with a typed DTO API.

Expose your application's calendars and contacts to any standards-compliant client — Apple Calendar, Apple Contacts, Thunderbird, DAVx⁵, and friends — backed by Eloquent and your own owner model.

## Features

- **Full CalDAV** — events (`VEVENT`), todos (`VTODO`), and journals (`VJOURNAL`).
- **Full CardDAV** — contacts (`VCARD`) in vCard 3.0 and 4.0, stored losslessly with version content-negotiation, plus rich, typed parsing.
- **WebDAV sync** — collection synchronization via sync tokens ([RFC 6578](https://datatracker.ietf.org/doc/html/rfc6578)).
- **CalDAV scheduling** ([RFC 6638](https://datatracker.ietf.org/doc/html/rfc6638)) — auto-scheduling between local users, schedule tags, free/busy queries, and optional iMIP email to external attendees ([RFC 6047](https://datatracker.ietf.org/doc/html/rfc6047)).
- **Calendar sharing** — CalendarServer-style sharing with read-only and read-write access.
- **Calendar proxy delegation** — account-level read and write delegation through CalDAV proxy principals.
- **CalDAV managed attachments** ([RFC 8607](https://datatracker.ietf.org/doc/html/rfc8607)) — server-managed calendar `ATTACH` files stored on a configurable Laravel filesystem disk.
- **Service discovery** — `/.well-known/caldav` and `/.well-known/carddav` redirects ([RFC 6764](https://datatracker.ietf.org/doc/html/rfc6764)).
- **HTTP Basic authentication** — stateless, backed by hashed credentials.
- **Owner-agnostic** — any model that implements a small contract can own collections.
- **Typed DTOs** — every calendar object and contact carries the verbatim `raw` payload plus best-effort, strongly-typed parsed fields.
- **Eloquent storage** — collections and objects are plain models you can query, extend, and relate to the rest of your app.

## Requirements

- PHP `^8.3`
- Laravel `^12` or `^13`
- sabre/dav `^4.7`

## Installation

```bash
composer require bambamboole/laravel-dav
```

The package auto-registers its service provider and loads its migrations, so `php artisan migrate` creates the tables. From there, implement the `DavOwner` contract on your `User` model, issue a client credential, and create a calendar — the [documentation](https://bambamboole.github.io/laravel-dav/) walks through each step.

## Documentation

Full documentation lives at **[bambamboole.github.io/laravel-dav](https://bambamboole.github.io/laravel-dav/)**:

- **Getting Started** — installation, owner setup, credentials, configuration, and connecting a client.
- **Core Concepts** — principals and owners, calendars, address books, sync tokens, and the raw/DTO data model.
- **Guides** — creating collections, calendar sharing, proxy delegation, calendar objects, contacts, scheduling, free/busy and availability, reacting to changes, and custom models.
- **Protocol Support** — WebDAV, CalDAV, CardDAV, discovery, authentication, locks, and client compatibility.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). See [LICENSE](LICENSE) for details.
