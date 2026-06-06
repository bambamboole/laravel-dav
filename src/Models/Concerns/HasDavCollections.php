<?php

namespace Bambamboole\LaravelDav\Models\Concerns;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Bambamboole\LaravelDav\Models\DavCalendarProxyMembership;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasDavCollections
{
    /**
     * @return HasMany<DavCalendar, $this>
     */
    public function davCalendars(): HasMany
    {
        return $this->hasMany(Dav::model(DavCalendar::class), 'owner_id');
    }

    /**
     * @return HasMany<DavCalendarInstance, $this>
     */
    public function davCalendarInstances(): HasMany
    {
        return $this->hasMany(Dav::model(DavCalendarInstance::class), 'owner_id');
    }

    /**
     * @return HasMany<DavAddressBook, $this>
     */
    public function davAddressBooks(): HasMany
    {
        return $this->hasMany(Dav::model(DavAddressBook::class), 'owner_id');
    }

    /**
     * @return HasMany<DavCalendarProxyMembership, $this>
     */
    public function davCalendarProxyMemberships(): HasMany
    {
        return $this->hasMany(Dav::model(DavCalendarProxyMembership::class), 'owner_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createDavCalendar(array $attributes = []): DavCalendar
    {
        return Dav::model(DavCalendar::class)::createForOwner($this->asDavOwner(), $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createDavAddressBook(array $attributes = []): DavAddressBook
    {
        return Dav::model(DavAddressBook::class)::createForOwner($this->asDavOwner(), $attributes);
    }

    public function grantCalendarProxy(DavOwner|int|string $delegate, string $access = DavCalendarProxyMembership::AccessRead): DavCalendarProxyMembership
    {
        return Dav::model(DavCalendarProxyMembership::class)::grant($this->asDavOwner(), $delegate, $access);
    }

    public function revokeCalendarProxy(DavOwner|int|string $delegate, ?string $access = null): void
    {
        Dav::model(DavCalendarProxyMembership::class)::revoke($this->asDavOwner(), $delegate, $access);
    }

    /**
     * @param  iterable<DavOwner|int|string>  $delegates
     */
    public function setCalendarProxyDelegates(string $access, iterable $delegates): void
    {
        Dav::model(DavCalendarProxyMembership::class)::setDelegates($this->asDavOwner(), $access, $delegates);
    }

    private function asDavOwner(): DavOwner
    {
        if (! $this instanceof DavOwner) {
            throw new \LogicException('The HasDavCollections trait can only be used on models implementing DavOwner.');
        }

        return $this;
    }
}
