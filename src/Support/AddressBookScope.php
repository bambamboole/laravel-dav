<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AddressBookScope
{
    public function __construct(
        private ContactCardWriter $contacts,
        private int|string $ownerId,
    ) {}

    /**
     * @return Collection<int, AddressBookHandle>
     */
    public function get(): Collection
    {
        return $this->query()
            ->get()
            ->map(fn (DavAddressBook $addressBook): AddressBookHandle => new AddressBookHandle($this->contacts, $addressBook));
    }

    public function find(int|string $id): ?AddressBookHandle
    {
        $addressBook = $this->resourceQuery($id)->first();

        return $addressBook instanceof DavAddressBook ? new AddressBookHandle($this->contacts, $addressBook) : null;
    }

    public function findOrFail(int|string $id): AddressBookHandle
    {
        return new AddressBookHandle($this->contacts, $this->resourceQuery($id)->firstOrFail());
    }

    /**
     * @return Builder<DavAddressBook>
     */
    private function query(): Builder
    {
        /** @var class-string<DavAddressBook> $model */
        $model = Dav::modelFor('address_book', DavAddressBook::class);

        return $model::query()
            ->where('user_id', $this->ownerId)
            ->orderBy('id');
    }

    /**
     * @return Builder<DavAddressBook>
     */
    private function resourceQuery(int|string $id): Builder
    {
        return $this->query()
            ->where(function (Builder $query) use ($id): void {
                if (is_numeric($id)) {
                    $query->whereKey($id);
                }

                $query->orWhere('uri', $id);
            });
    }
}
