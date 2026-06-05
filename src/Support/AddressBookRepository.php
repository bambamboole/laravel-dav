<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Contracts\DavOwner;

class AddressBookRepository
{
    use ResolvesDavOwnerId;

    public function __construct(
        private ContactCardWriter $contacts,
    ) {}

    public function for(DavOwner|int|string $owner): AddressBookScope
    {
        return new AddressBookScope($this->contacts, $this->davOwnerId($owner));
    }
}
