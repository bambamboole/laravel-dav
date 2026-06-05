---
title: Configuration
description: Configure owner models, routes, authentication, scheduling, and swappable models.
---

Publish the config file when you need to override package defaults:

```bash
php artisan vendor:publish --tag=dav-config
```

The published file is `config/dav.php`.

## Models

The owner model defaults to `App\Models\User`. It must implement `Bambamboole\LaravelDav\Contracts\DavOwner`.

```php
'models' => [
    'owner' => App\Models\User::class,
],
```

Content models can also be replaced with subclasses of the package models:

```php
'models' => [
    'calendar' => App\Models\TeamCalendar::class,
    'card' => App\Models\ContactCard::class,
],
```

Available model keys include `calendar`, `calendar_instance`, `calendar_object`, `calendar_attachment`, `calendar_subscription`, `calendar_proxy_membership`, `address_book`, `card`, `credential`, and `scheduling_object`.

## Routes

The DAV endpoint defaults to `/dav/`:

```php
'route' => [
    'prefix' => 'dav',
    'middleware' => [],
],
```

Change `route.prefix` to serve DAV traffic from another path:

```php
'route' => [
    'prefix' => 'remote.php/dav',
    'middleware' => [],
],
```

When `base_uri` is `null`, the advertised Sabre base URI is derived from `route.prefix`. Set `base_uri` only when the externally visible DAV path differs from the Laravel route path:

```php
'base_uri' => '/remote.php/dav/',
```

## Authentication

The Basic authentication realm defaults to your Laravel application name:

```php
'realm' => config('app.name', 'Laravel'),
```

Use `DavCredential` records for per-client usernames and hashed secrets.

## Paths

The internal DAV path segments are configurable:

```php
'principal_prefix' => 'principals',
'calendar_prefix' => 'calendars',
'address_book_prefix' => 'addressbooks',
'default_calendar_uri' => 'personal',
'default_address_book_uri' => 'personal',
```

Only change these before clients start syncing. Existing clients may cache discovered collection URLs.

## Scheduling

Scheduling is enabled by default:

```php
'scheduling' => [
    'enabled' => true,
    'mailer' => env('MAIL_MAILER'),
    'from' => env('DAV_SCHEDULING_FROM', env('MAIL_FROM_ADDRESS', 'noreply@laravel-dav.example')),
],
```

Disable it if your application only needs local storage and sync:

```php
'scheduling' => [
    'enabled' => false,
],
```
