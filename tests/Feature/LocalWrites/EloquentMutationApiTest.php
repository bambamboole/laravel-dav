<?php

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavCalendarProxyMembership;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Models\DavChange;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Carbon\CarbonImmutable;

it('creates and mutates dav resources through eloquent models', function (): void {
    $owner = OwnerUser::factory()->create();
    $sharee = OwnerUser::factory()->create();

    $calendar = $owner->createDavCalendar([
        'uri' => 'work',
        'display_name' => 'Work',
        'description' => 'Team calendar',
        'color' => '#ff0000ff',
        'components' => ['VEVENT'],
    ]);

    expect($calendar)->toBeInstanceOf(DavCalendar::class)
        ->owner_id->toBe($owner->getKey())
        ->components->toBe(['VEVENT'])
        ->and($calendar->ownerInstance)->toBeInstanceOf(DavCalendarInstance::class)
        ->and($calendar->ownerInstance->uri)->toBe('work')
        ->and($calendar->ownerInstance->display_name)->toBe('Work');

    $calendar->ownerInstance->updateDavProperties([
        'display_name' => 'Engineering',
        'color' => '#00ff00ff',
        'components' => ['VEVENT', 'VTODO'],
    ]);

    expect($calendar->refresh()->components)->toBe(['VEVENT', 'VTODO'])
        ->and($calendar->ownerInstance->refresh()->display_name)->toBe('Engineering')
        ->and($calendar->ownerInstance->color)->toBe('#00ff00ff');

    $object = $calendar->putObject(new CalendarObjectData(
        uri: '',
        raw: '',
        etag: '',
        size: 0,
        uid: 'event-1',
        summary: 'Planning',
        startsAt: CarbonImmutable::parse('2026-01-01 09:00:00', 'UTC'),
        endsAt: CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC'),
        timezone: 'UTC',
    ));

    expect($object)->toBeInstanceOf(DavCalendarObject::class)
        ->uri->toBe('event-1.ics')
        ->and($object->calendar_data)->toContain('SUMMARY:Planning')
        ->and($calendar->fresh()->sync_token)->toBe(2);

    $etag = $object->etag;

    $object->replaceWith(new CalendarObjectData(
        uri: $object->uri,
        raw: $object->calendar_data,
        etag: $object->etag,
        size: $object->size,
        uid: $object->uid,
        summary: 'Refined planning',
        startsAt: CarbonImmutable::parse('2026-01-01 09:00:00', 'UTC'),
        endsAt: CarbonImmutable::parse('2026-01-01 10:00:00', 'UTC'),
        timezone: 'UTC',
    ), expectedEtag: $etag);

    expect($object->refresh()->calendar_data)->toContain('SUMMARY:Refined planning')
        ->and($object->etag)->not->toBe($etag);

    $object->deleteDavResource(expectedEtag: $object->etag);

    $this->assertModelMissing($object);
    expect($calendar->fresh()->sync_token)->toBe(4)
        ->and(DavChange::query()->where('collection_type', 'calendar')->count())->toBe(3);

    $sharedInstance = $calendar->shareWith($sharee, DavCalendarInstance::AccessReadWrite, shareDisplayName: 'Shared work');

    expect($sharedInstance)->toBeInstanceOf(DavCalendarInstance::class)
        ->owner_id->toBe($sharee->getKey())
        ->access->toBe(DavCalendarInstance::AccessReadWrite)
        ->display_name->toBe('Engineering')
        ->share_display_name->toBe('Shared work');

    $sharedInstance->deleteDavCollection();

    $this->assertModelMissing($sharedInstance);
    $this->assertModelExists($calendar);

    $calendar->ownerInstance->deleteDavCollection();

    $this->assertModelMissing($calendar);
});

it('creates and mutates address book contacts through eloquent models', function (): void {
    $owner = OwnerUser::factory()->create();

    $addressBook = $owner->createDavAddressBook([
        'uri' => 'people',
        'display_name' => 'People',
        'description' => 'Address book',
    ]);

    expect($addressBook)->toBeInstanceOf(DavAddressBook::class)
        ->owner_id->toBe($owner->getKey())
        ->uri->toBe('people')
        ->display_name->toBe('People');

    $addressBook->updateDavProperties([
        'display_name' => 'Contacts',
        'description' => 'Main contacts',
    ]);

    expect($addressBook->refresh()->display_name)->toBe('Contacts')
        ->and($addressBook->description)->toBe('Main contacts');

    $card = $addressBook->putContact(new ContactData(
        uri: '',
        raw: '',
        etag: '',
        size: 0,
        uid: 'contact-1',
        formattedName: 'Ada Lovelace',
    ));

    expect($card)->toBeInstanceOf(DavCard::class)
        ->uri->toBe('contact-1.vcf')
        ->and($card->card_data)->toContain('FN:Ada Lovelace')
        ->and($addressBook->fresh()->sync_token)->toBe(2);

    $etag = $card->etag;

    $card->replaceWith(new ContactData(
        uri: $card->uri,
        raw: $card->card_data,
        etag: $card->etag,
        size: $card->size,
        uid: $card->data->uid,
        formattedName: 'Countess Lovelace',
    ), expectedEtag: $etag);

    expect($card->refresh()->card_data)->toContain('FN:Countess Lovelace')
        ->and($card->etag)->not->toBe($etag);

    $card->deleteDavResource(expectedEtag: $card->etag);

    $this->assertModelMissing($card);
    expect($addressBook->fresh()->sync_token)->toBe(4);

    $addressBook->deleteDavAddressBook();

    $this->assertModelMissing($addressBook);
});

it('manages calendar proxy delegates from the owner model', function (): void {
    $owner = OwnerUser::factory()->create();
    $readDelegate = OwnerUser::factory()->create();
    $writeDelegate = OwnerUser::factory()->create();

    $membership = $owner->grantCalendarProxy($writeDelegate, DavCalendarProxyMembership::AccessWrite);

    expect($membership)->toBeInstanceOf(DavCalendarProxyMembership::class)
        ->owner_id->toBe($owner->getKey())
        ->delegate_owner_id->toBe($writeDelegate->getKey())
        ->access->toBe(DavCalendarProxyMembership::AccessWrite);

    $owner->setCalendarProxyDelegates(DavCalendarProxyMembership::AccessRead, [$readDelegate, $writeDelegate]);

    expect(DavCalendarProxyMembership::query()
        ->where('owner_id', $owner->getKey())
        ->where('access', DavCalendarProxyMembership::AccessRead)
        ->pluck('delegate_owner_id')
        ->sort()
        ->values()
        ->all())->toBe([$readDelegate->getKey(), $writeDelegate->getKey()]);

    $owner->revokeCalendarProxy($writeDelegate);

    expect(DavCalendarProxyMembership::query()
        ->where('owner_id', $owner->getKey())
        ->where('delegate_owner_id', $writeDelegate->getKey())
        ->exists())->toBeFalse();
});
