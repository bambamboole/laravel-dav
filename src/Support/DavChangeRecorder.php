<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Events\DavCollectionChanged;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavChange;
use Closure;
use Illuminate\Support\Facades\DB;

class DavChangeRecorder
{
    public const CalendarCollectionType = 'calendar';

    public const AddressBookCollectionType = 'address_book';

    private static bool $recording = true;

    /**
     * Run the callback without recording any DAV changes (e.g. seeding or
     * bulk imports that should not generate sync history).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutRecording(Closure $callback): mixed
    {
        $previous = self::$recording;
        self::$recording = false;

        try {
            return $callback();
        } finally {
            self::$recording = $previous;
        }
    }

    public function record(DavCalendar|DavAddressBook $collection, ?string $resourceUri, DavChangeOperation $operation): void
    {
        if (! self::$recording) {
            return;
        }

        $type = $collection instanceof DavCalendar
            ? self::CalendarCollectionType
            : self::AddressBookCollectionType;

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
