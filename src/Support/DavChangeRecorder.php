<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Events\DavCollectionChanged;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavChange;
use Illuminate\Support\Facades\DB;

class DavChangeRecorder
{
    public const CalendarCollectionType = 'calendar';

    public const AddressBookCollectionType = 'address_book';

    public function recordCalendarChange(DavCalendar $calendar, ?string $resourceUri, DavChangeOperation $operation): void
    {
        $this->recordChange($calendar, self::CalendarCollectionType, $resourceUri, $operation);
    }

    public function recordAddressBookChange(DavAddressBook $addressBook, ?string $resourceUri, DavChangeOperation $operation): void
    {
        $this->recordChange($addressBook, self::AddressBookCollectionType, $resourceUri, $operation);
    }

    public function recordChange(DavCalendar|DavAddressBook $collection, string $type, ?string $resourceUri, DavChangeOperation $operation): void
    {
        DB::transaction(function () use ($collection, $type, $resourceUri, $operation): void {
            $lockedCollection = $collection->newQuery()
                ->whereKey($collection->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCollection->forceFill([
                'sync_token' => $lockedCollection->sync_token + 1,
            ])->save();

            DavChange::query()->create([
                'collection_type' => $type,
                'collection_id' => $lockedCollection->getKey(),
                'resource_uri' => $resourceUri,
                'operation' => $operation->value,
                'sync_token' => $lockedCollection->sync_token,
            ]);

            DavCollectionChanged::dispatch(
                (int) $lockedCollection->user_id,
                $type,
                (int) $lockedCollection->getKey(),
                $resourceUri,
                $operation->label(),
                (int) $lockedCollection->sync_token,
            );
        });
    }
}
