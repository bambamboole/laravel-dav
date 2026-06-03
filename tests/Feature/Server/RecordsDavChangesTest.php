<?php

use Bambamboole\LaravelDav\Events\DavCollectionChanged;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavChange;
use Bambamboole\LaravelDav\Sabre\Concerns\RecordsDavChanges;
use Illuminate\Support\Facades\Event;

function recorder(): object
{
    return new class
    {
        use RecordsDavChanges;

        public function calendar(DavCalendar $calendar, ?string $resourceUri, int $operation): void
        {
            $this->recordCalendarChange($calendar, $resourceUri, $operation);
        }
    };
}

it('increments the sync token, writes a change row, and dispatches the event', function (): void {
    Event::fake([DavCollectionChanged::class]);

    $calendar = DavCalendar::factory()->create(['sync_token' => 1]);

    recorder()->calendar($calendar, 'event.ics', 1);

    expect($calendar->fresh()->sync_token)->toBe(2);

    $change = DavChange::query()->latest('id')->firstOrFail();

    expect($change->collection_type)->toBe('calendar')
        ->and($change->collection_id)->toBe($calendar->getKey())
        ->and($change->resource_uri)->toBe('event.ics')
        ->and($change->operation)->toBe(1)
        ->and($change->sync_token)->toBe(2);

    Event::assertDispatched(DavCollectionChanged::class, function (DavCollectionChanged $event) use ($calendar): bool {
        return $event->ownerId === (int) $calendar->user_id
            && $event->type === 'calendar'
            && $event->collectionId === $calendar->getKey()
            && $event->resourceUri === 'event.ics'
            && $event->operation === 'added'
            && $event->syncToken === 2;
    });
});
