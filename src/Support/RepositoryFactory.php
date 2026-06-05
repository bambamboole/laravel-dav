<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Contracts\DavOwner;

class RepositoryFactory
{
    public function __construct(
        private ContactCardWriter $contacts,
        private CalendarObjectWriter $calendarObjects,
    ) {}

    public function contacts(DavOwner|int|string $owner): ContactCardRepository
    {
        return new ContactCardRepository($this->contacts, $this->davOwnerId($owner));
    }

    public function calendarObjects(DavOwner|int|string $owner): CalendarObjectRepository
    {
        return new CalendarObjectRepository($this->calendarObjects, $this->davOwnerId($owner));
    }

    public function addressBooks(DavOwner|int|string $owner): AddressBookRepository
    {
        return new AddressBookRepository($this->contacts, $this->davOwnerId($owner));
    }

    public function calendars(DavOwner|int|string $owner): CalendarRepository
    {
        return new CalendarRepository($this->calendarObjects, $this->davOwnerId($owner));
    }

    private function davOwnerId(DavOwner|int|string $owner): int|string
    {
        return $owner instanceof DavOwner ? $owner->getDavPrincipalId() : $owner;
    }
}
