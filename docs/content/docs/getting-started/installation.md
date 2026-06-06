---
title: Installation
description: Install the package and publish optional assets.
---

## Quick install

Install the package with Composer:

```bash
composer require bambamboole/laravel-dav
```

The service provider is auto-discovered. Package migrations are loaded automatically, so run your application migrations to create the DAV tables:

```bash
php artisan migrate
```

## Requirements

- PHP `^8.3`
- Laravel `^12` or `^13`
- sabre/dav `^4.7`

Publishing is optional. Publish the config file when you need to customize the owner model, route prefix, Basic auth realm, scheduling mailer, or model classes:

```bash
php artisan vendor:publish --tag=dav-config
```

Publish migrations only when you want to customize the schema before the first migration run:

```bash
php artisan vendor:publish --tag=dav-migrations
```

## Default endpoint

DAV traffic is served under the configured route prefix:

```text
https://your-app.test/dav/
```

The well-known URLs redirect into that endpoint:

```text
/.well-known/caldav  -> /dav/
/.well-known/carddav -> /dav/
```
