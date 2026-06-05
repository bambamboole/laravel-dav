<?php

use Illuminate\Support\Facades\Schema;

it('creates every dav table', function (string $table): void {
    expect(Schema::hasTable($table))->toBeTrue();
})->with([
    'dav_credentials',
    'dav_address_books',
    'dav_calendars',
    'dav_changes',
    'dav_properties',
    'dav_cards',
    'dav_locks',
    'dav_calendar_objects',
]);

it('has the key columns on dav_calendar_objects', function (): void {
    expect(Schema::hasColumns('dav_calendar_objects', [
        'dav_calendar_id',
        'uri',
        'uid',
        'component_type',
        'summary',
        'starts_at',
        'ends_at',
        'is_all_day',
        'status',
        'url',
        'etag',
        'size',
        'calendar_data',
    ]))->toBeTrue();
});

it('has the key columns on dav_cards', function (): void {
    expect(Schema::hasColumns('dav_cards', [
        'dav_address_book_id',
        'uri',
        'uid',
        'full_name',
        'contact_type',
        'birthday',
        'email_addresses',
        'phone_numbers',
        'etag',
        'size',
        'card_data',
    ]))->toBeTrue();
});

it('does not create denormalized simple contact columns', function (): void {
    expect(Schema::hasColumn('dav_cards', 'emails'))->toBeFalse()
        ->and(Schema::hasColumn('dav_cards', 'phones'))->toBeFalse();
});
