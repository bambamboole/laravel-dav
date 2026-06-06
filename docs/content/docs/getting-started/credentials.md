---
title: Credentials
description: Create per-client HTTP Basic credentials.
---

DAV clients authenticate with HTTP Basic. Each client should get its own `DavCredential`, rather than reusing the user’s application password.

Generate a random secret, store only its hash, and show the plaintext to the user once:

```php
use Bambamboole\LaravelDav\Models\DavCredential;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$secret = Str::random(32);

DavCredential::create([
    'owner_id' => $user->id,
    'name' => 'iPhone',
    'username' => 'iphone-'.$user->id,
    'secret_hash' => Hash::make($secret),
]);
```

The client signs in with `username` and the plaintext `$secret`. On successful requests, the package updates `last_used_at`.

## Security model

Only Basic authentication is supported. Deploy behind HTTPS and issue revocable per-client secrets.

Digest authentication is intentionally not implemented because it requires storing Digest-compatible HA1 material instead of normal password hashes.

## Realm

The Basic authentication realm defaults to your Laravel application name. Override it with:

```dotenv
DAV_REALM="Your App DAV"
```
