<?php

namespace Bambamboole\LaravelDav\Sabre\CardDav;

use Bambamboole\LaravelDav\LaravelDav;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Sabre\Concerns\RecordsDavChanges;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use Illuminate\Support\Facades\DB;
use Sabre\CardDAV\Backend\AbstractBackend;
use Sabre\CardDAV\Backend\SyncSupport;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\PropPatch;

class AddressBookBackend extends AbstractBackend implements SyncSupport
{
    use RecordsDavChanges;
    use ResolvesPrincipalUri;

    private const DisplayNameProperty = '{DAV:}displayname';

    private const DescriptionProperty = '{urn:ietf:params:xml:ns:carddav}addressbook-description';

    private const CtagProperty = '{http://calendarserver.org/ns/}getctag';

    private const SyncTokenProperty = '{http://sabredav.org/ns}sync-token';

    public function __construct(private UpsertContactCard $upsertContactCard) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAddressBooksForUser($principalUri): array
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null) {
            return [];
        }

        return LaravelDav::modelFor('address_book', DavAddressBook::class)::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get()
            ->map(fn (DavAddressBook $addressBook): array => $this->addressBookRow($addressBook))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function createAddressBook($principalUri, $url, array $properties): int
    {
        $userId = $this->userIdFromPrincipalUri((string) $principalUri);

        if ($userId === null || ! $this->ownerExists($userId)) {
            throw new NotFound('Principal not found');
        }

        $addressBook = LaravelDav::modelFor('address_book', DavAddressBook::class)::query()->create([
            'user_id' => $userId,
            'uri' => (string) $url,
            'display_name' => (string) ($properties[self::DisplayNameProperty] ?? $url),
            'description' => $properties[self::DescriptionProperty] ?? null,
            'sync_token' => 1,
        ]);

        return (int) $addressBook->id;
    }

    public function updateAddressBook($addressBookId, PropPatch $propPatch): void
    {
        $propPatch->handle([
            self::DisplayNameProperty,
            self::DescriptionProperty,
        ], function (array $mutations) use ($addressBookId): bool {
            $addressBook = LaravelDav::model('address_book')::query()->find($addressBookId);

            if (! $addressBook) {
                return false;
            }

            $values = [];

            foreach ($mutations as $property => $value) {
                match ($property) {
                    self::DisplayNameProperty => $values['display_name'] = $value,
                    self::DescriptionProperty => $values['description'] = $value,
                    default => null,
                };
            }

            $addressBook->forceFill($values)->save();

            return true;
        });
    }

    public function deleteAddressBook($addressBookId): void
    {
        LaravelDav::model('address_book')::query()->whereKey($addressBookId)->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCards($addressbookId): array
    {
        return $this->addressBook($addressbookId)->cards()
            ->select([
                'id',
                'dav_address_book_id',
                'uri',
                'etag',
                'size',
                'last_modified_at',
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (DavCard $card): array => $this->cardRow($card))
            ->all();
    }

    /**
     * @return array<string, mixed>|false
     */
    public function getCard($addressBookId, $cardUri): array|false
    {
        $card = $this->addressBook($addressBookId)->cards()
            ->where('uri', $cardUri)
            ->first();

        return $card ? $this->cardRow($card, includeData: true) : false;
    }

    /**
     * @param  array<int, string>  $uris
     * @return array<int, array<string, mixed>>
     */
    public function getMultipleCards($addressBookId, array $uris): array
    {
        if ($uris === []) {
            return [];
        }

        return $this->addressBook($addressBookId)->cards()
            ->whereIn('uri', $uris)
            ->orderBy('id')
            ->get()
            ->map(fn (DavCard $card): array => $this->cardRow($card, includeData: true))
            ->all();
    }

    public function createCard($addressBookId, $cardUri, $cardData): string
    {
        $card = DB::transaction(function () use ($addressBookId, $cardUri, $cardData): DavCard {
            $addressBook = $this->addressBook($addressBookId);
            $card = $this->upsertContactCard->handle(
                $addressBook,
                (string) $cardUri,
                (string) $cardData,
            );

            $this->recordAddressBookChange($addressBook, (string) $cardUri, self::OperationAdd);

            return $card;
        });

        return '"'.$card->etag.'"';
    }

    public function updateCard($addressBookId, $cardUri, $cardData): string
    {
        $card = DB::transaction(function () use ($addressBookId, $cardUri, $cardData): DavCard {
            $addressBook = $this->addressBook($addressBookId);
            $card = $this->upsertContactCard->handle(
                $addressBook,
                (string) $cardUri,
                (string) $cardData,
            );

            $this->recordAddressBookChange($addressBook, (string) $cardUri, self::OperationModify);

            return $card;
        });

        return '"'.$card->etag.'"';
    }

    public function deleteCard($addressBookId, $cardUri): bool
    {
        return DB::transaction(function () use ($addressBookId, $cardUri): bool {
            $addressBook = $this->addressBook($addressBookId);
            $deleted = (bool) $addressBook->cards()
                ->where('uri', $cardUri)
                ->delete();

            if ($deleted) {
                $this->recordAddressBookChange($addressBook, (string) $cardUri, self::OperationDelete);
            }

            return $deleted;
        });
    }

    /**
     * @return array{syncToken: string, added: array<int, string>, modified: array<int, string>, deleted: array<int, string>}|null
     */
    public function getChangesForAddressBook($addressBookId, $syncToken, $syncLevel, $limit = null): ?array
    {
        $addressBook = LaravelDav::modelFor('address_book', DavAddressBook::class)::query()->find($addressBookId);
        $syncToken = (string) $syncToken;

        if (! $addressBook) {
            return null;
        }

        if ($syncToken === '') {
            return $this->currentResourceChangeResponse(
                $addressBook->sync_token,
                $addressBook->cards()->orderBy('id')->getQuery(),
                $limit,
            );
        }

        return $this->changedResourceResponse($addressBook, self::AddressBookCollectionType, $syncToken, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    private function addressBookRow(DavAddressBook $addressBook): array
    {
        return [
            'id' => $addressBook->id,
            'uri' => $addressBook->uri,
            'principaluri' => $this->principalUri($addressBook->user_id),
            self::DisplayNameProperty => $addressBook->display_name,
            self::DescriptionProperty => $addressBook->description,
            self::CtagProperty => (string) $addressBook->sync_token,
            self::SyncTokenProperty => $this->davSyncToken($addressBook->sync_token),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cardRow(DavCard $card, bool $includeData = false): array
    {
        $row = [
            'id' => $card->id,
            'uri' => $card->uri,
            'lastmodified' => $card->last_modified_at->getTimestamp(),
            'etag' => '"'.$card->etag.'"',
            'size' => $card->size,
            'addressbookid' => $card->dav_address_book_id,
        ];

        if ($includeData) {
            $row['carddata'] = $card->card_data;
        }

        return $row;
    }

    private function addressBook(int|string $addressBookId): DavAddressBook
    {
        return LaravelDav::modelFor('address_book', DavAddressBook::class)::query()->findOrFail($addressBookId);
    }

    private function ownerExists(int $userId): bool
    {
        $model = config('dav.owner_model');

        return $model::query()->whereKey($userId)->exists();
    }
}
