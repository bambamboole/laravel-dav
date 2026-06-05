<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\AddressBookData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AddressBookRepository
{
    public function __construct(
        private ContactCardWriter $contacts,
        private int|string $ownerId,
    ) {}

    /**
     * @return Collection<int, AddressBookData>
     */
    public function get(): Collection
    {
        return $this->query()
            ->get()
            ->map(fn (DavAddressBook $addressBook): AddressBookData => $this->data($addressBook));
    }

    public function find(int|string $id): ?AddressBookData
    {
        $addressBook = $this->resourceQuery($id)->first();

        return $addressBook instanceof DavAddressBook ? $this->data($addressBook) : null;
    }

    public function findOrFail(int|string $id): AddressBookData
    {
        return $this->data($this->resourceQuery($id)->firstOrFail());
    }

    public function create(AddressBookData $data): AddressBookData
    {
        $addressBook = $this->modelClass()::query()->create([
            'user_id' => $this->ownerId,
            'uri' => $data->uri,
            'display_name' => $data->displayName ?? $data->uri,
            'description' => $data->description,
            'sync_token' => $data->syncToken,
        ]);

        return $this->data($addressBook->refresh());
    }

    public function update(DavAddressBook|int|string $addressBook, AddressBookData $data): AddressBookData
    {
        return DB::transaction(function () use ($addressBook, $data): AddressBookData {
            $model = $this->model($addressBook);

            $model->forceFill([
                'uri' => $data->uri,
                'display_name' => $data->displayName ?? $data->uri,
                'description' => $data->description,
            ])->save();

            return $this->data($model->refresh());
        });
    }

    public function delete(DavAddressBook|int|string $addressBook): void
    {
        $this->model($addressBook)->delete();
    }

    public function contacts(DavAddressBook|int|string $addressBook): ContactCardRepository
    {
        $model = $this->model($addressBook);

        return new ContactCardRepository($this->contacts, $this->ownerId, $model);
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

    private function model(DavAddressBook|int|string $addressBook): DavAddressBook
    {
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

    private function data(DavAddressBook $addressBook): AddressBookData
    {
        return new AddressBookData(
            uri: $addressBook->uri,
            displayName: $addressBook->display_name,
            description: $addressBook->description,
            syncToken: $addressBook->sync_token,
        );
    }
}
