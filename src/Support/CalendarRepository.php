<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Contracts\DavOwner;

class CalendarRepository
{
    use ResolvesDavOwnerId;

    public function __construct(
        private CalendarObjectWriter $objects,
    ) {}

    public function for(DavOwner|int|string $owner): CalendarScope
    {
        return new CalendarScope($this->objects, $this->davOwnerId($owner));
    }
}
