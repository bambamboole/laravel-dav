<?php

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Exceptions\StaleDavResourceException;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavChange;
use Carbon\CarbonImmutable;

it('creates a typed calendar object and records a sync change', function (): void {
    $calendar = DavCalendar::factory()->create(['sync_token' => 1]);

    $object = Dav::calendarObjects()->create($calendar, new CalendarObjectData(
        uri: '',
        raw: '',
        etag: '',
        size: 0,
        uid: 'event-1',
        componentType: 'VEVENT',
        summary: 'Sprint planning',
        startsAt: CarbonImmutable::parse('2026-01-01 09:00:00', 'UTC'),
        endsAt: CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC'),
        timezone: 'UTC',
    ));

    expect($object)->toBeInstanceOf(DavCalendarObject::class)
        ->and($object->uri)->toBe('event-1.ics')
        ->and($object->summary)->toBe('Sprint planning')
        ->and($object->calendar_data)->toContain('SUMMARY:Sprint planning')
        ->and($calendar->fresh()->sync_token)->toBe(2);

    expect(DavChange::query()->where('collection_type', 'calendar')->where('operation', 1)->count())->toBe(1);
});

it('updates a typed calendar object with optimistic concurrency', function (): void {
    $object = DavCalendarObject::factory()->create(['summary' => 'Old']);
    $etag = $object->etag;

    $updated = Dav::calendarObjects()->update($object, new CalendarObjectData(
        uri: $object->uri,
        raw: $object->calendar_data,
        etag: $object->etag,
        size: $object->size,
        uid: $object->uid,
        componentType: 'VEVENT',
        summary: 'New',
        startsAt: CarbonImmutable::parse('2026-01-01 09:00:00', 'UTC'),
        endsAt: CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC'),
        timezone: 'UTC',
    ), expectedEtag: $etag);

    expect($updated->summary)->toBe('New')
        ->and($updated->calendar_data)->toContain('SUMMARY:New')
        ->and($updated->etag)->not->toBe($etag);
});

it('rejects stale calendar object updates', function (): void {
    $object = DavCalendarObject::factory()->create();

    expect(fn () => Dav::calendarObjects()->update($object, $object->toData(), expectedEtag: 'stale'))
        ->toThrow(StaleDavResourceException::class);
});

it('deletes a typed calendar object with optimistic concurrency', function (): void {
    $object = DavCalendarObject::factory()->create();
    $calendar = $object->calendar;

    Dav::calendarObjects()->delete($object, expectedEtag: $object->etag);

    expect(DavCalendarObject::query()->whereKey($object->getKey())->exists())->toBeFalse()
        ->and($calendar->fresh()->sync_token)->toBe(2)
        ->and(DavChange::query()->where('collection_type', 'calendar')->where('operation', 3)->count())->toBe(1);
});
