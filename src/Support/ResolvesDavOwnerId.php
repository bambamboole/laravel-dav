<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Contracts\DavOwner;

trait ResolvesDavOwnerId
{
    private function davOwnerId(DavOwner|int|string $owner): int|string
    {
        return $owner instanceof DavOwner ? $owner->getDavPrincipalId() : $owner;
    }
}
