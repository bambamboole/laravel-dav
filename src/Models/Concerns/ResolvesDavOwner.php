<?php

namespace Bambamboole\LaravelDav\Models\Concerns;

use Bambamboole\LaravelDav\Contracts\DavOwner;

trait ResolvesDavOwner
{
    protected static function resolveOwnerId(DavOwner|int|string $owner): int|string
    {
        return $owner instanceof DavOwner ? $owner->getDavPrincipalId() : $owner;
    }
}
