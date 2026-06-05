<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\AddressBookData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function create(AddressBookData $data): AddressBookHandle
    {
        $addressBook = $this->modelClass()::query()->create([
            'user_id' => $this->ownerId,
            'uri' => $data->uri,
            'display_name' => $data->displayName ?? $data->uri,
            'description' => $data->description,
            'sync_token' => $data->syncToken,
        ]);

        return new AddressBookHandle($this->contacts, $addressBook->refresh());
    }

    public function update(DavAddressBook|AddressBookHandle|int|string $addressBook, AddressBookData $data): AddressBookHandle
    {
        return DB::transaction(function () use ($addressBook, $data): AddressBookHandle {
            $model = $this->model($addressBook);

            $model->forceFill([
                'uri' => $data->uri,
                'display_name' => $data->displayName ?? $data->uri,
                'description' => $data->description,
            ])->save();

            return new AddressBookHandle($this->contacts, $model->refresh());
        });
    }

    public function delete(DavAddressBook|AddressBookHandle|int|string $addressBook): void
    {
        $this->model($addressBook)->delete();
    }

    /**
     * @return Builder<DavAddressBook>
     */
    private function query(): Builder
    {
        return $this->modelClass()::query()
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

    private function model(DavAddressBook|AddressBookHandle|int|string $addressBook): DavAddressBook
    {
        if ($addressBook instanceof AddressBookHandle) {
            $addressBook = $addressBook->model;
        }

        return $addressBook instanceof DavAddressBook
            ? $this->resourceQuery((string) $addressBook->getKey())->firstOrFail()
            : $this->resourceQuery($addressBook)->firstOrFail();
    }

    /**
     * @return class-string<DavAddressBook>
     */
    private function modelClass(): string
    {
        return Dav::modelFor('address_book', DavAddressBook::class);
    }
}
