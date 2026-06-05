<?php

use Bambamboole\LaravelDav\Events\DavCollectionChanged;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavChange;
use Bambamboole\LaravelDav\Support\DavChangeOperation;
use Bambamboole\LaravelDav\Support\DavChangeRecorder;
use Illuminate\Support\Facades\Event;

it('increments the sync token, writes a change row, and dispatches the event', function (): void {
    Event::fake([DavCollectionChanged::class]);

    $calendar = DavCalendar::factory()->create(['sync_token' => 1]);

    app(DavChangeRecorder::class)->record($calendar, 'event.ics', DavChangeOperation::Add);

    expect($calendar->fresh()->sync_token)->toBe(2);

    $change = DavChange::query()->latest('id')->firstOrFail();

    expect($change->collection_type)->toBe('calendar')
        ->and($change->collection_id)->toBe($calendar->getKey())
        ->and($change->resource_uri)->toBe('event.ics')
        ->and($change->operation)->toBe(DavChangeOperation::Add->value)
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

it('uses the enum as the single source for persisted operation values and labels', function (): void {
    expect(DavChangeOperation::Add->value)->toBe(1)
        ->and(DavChangeOperation::Modify->value)->toBe(2)
        ->and(DavChangeOperation::Delete->value)->toBe(3)
        ->and(DavChangeOperation::Add->label())->toBe('added')
        ->and(DavChangeOperation::Modify->label())->toBe('modified')
        ->and(DavChangeOperation::Delete->label())->toBe('deleted');
});
