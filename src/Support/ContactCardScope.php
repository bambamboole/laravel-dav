<?php

namespace Bambamboole\LaravelDav\Support;

use BadMethodCallException;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ContactCardScope
{
    public function __construct(
        private ContactCardWriter $writer,
        private int|string $ownerId,
        private ?DavAddressBook $addressBook = null,
    ) {}

    /**
     * @return Collection<int, ContactData>
     */
    public function get(): Collection
    {
        return $this->query()
            ->get()
            ->map(fn (DavCard $card): ContactData => $card->toData());
    }

    public function find(int|string $id): ?ContactData
    {
        return $this->resourceQuery($id)->first()?->toData();
    }

    public function findOrFail(int|string $id): ContactData
    {
        return $this->resourceQuery($id)->firstOrFail()->toData();
    }

    public function create(ContactData $data): DavCard
    {
        if ($this->addressBook === null) {
            throw new BadMethodCallException('Contacts can only be created through an address book scope.');
        }

        return $this->writer->create($this->addressBook, $data);
    }

    public function update(DavCard|int|string $card, ContactData $data, string $expectedEtag): DavCard
    {
        return $this->writer->update($this->model($card), $data, $expectedEtag);
    }

    public function forceUpdate(DavCard|int|string $card, ContactData $data): DavCard
    {
        return $this->writer->forceUpdate($this->model($card), $data);
    }

    public function delete(DavCard|int|string $card, string $expectedEtag): void
    {
        $this->writer->delete($this->model($card), $expectedEtag);
    }

    public function forceDelete(DavCard|int|string $card): void
    {
        $this->writer->forceDelete($this->model($card));
    }

    /**
     * @return Builder<DavCard>
     */
    private function query(): Builder
    {
        if ($this->addressBook !== null) {
            return $this->addressBook->cards()->getQuery()->orderBy('id');
        }

        /** @var class-string<DavCard> $model */
        $model = Dav::modelFor('card', DavCard::class);

        return $model::query()
            ->whereHas('addressBook', fn (Builder $query): Builder => $query->where('user_id', $this->ownerId))
            ->orderBy('id');
    }

    /**
     * @return Builder<DavCard>
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

    private function model(DavCard|int|string $card): DavCard
    {
        return $card instanceof DavCard
            ? $this->resourceQuery((string) $card->getKey())->firstOrFail()
            : $this->resourceQuery($card)->firstOrFail();
    }
}
