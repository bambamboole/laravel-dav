<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarSubscription;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Sabre\CalDAV\Backend\SubscriptionSupport;
use Sabre\CalDAV\Xml\Property\SupportedCalendarComponentSet;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Xml\Property\Href;

function subscriptionBackend(): CalendarBackend
{
    return app(CalendarBackend::class);
}

it('returns subscriptions for a principal', function (): void {
    $owner = OwnerUser::factory()->create();

    DavCalendarSubscription::factory()->create([
        'owner_id' => $owner->getKey(),
        'uri' => 'holidays',
        'source' => 'https://example.com/holidays.ics',
        'display_name' => 'Holidays',
        'color' => '#B8255FFF',
        'refresh_rate' => 'P1D',
        'order' => 10,
        'strip_todos' => true,
        'strip_alarms' => true,
        'strip_attachments' => true,
        'last_modified_at' => now(),
    ]);

    $subscriptions = subscriptionBackend()->getSubscriptionsForUser('principals/'.$owner->getKey());

    expect($subscriptions)->toHaveCount(1)
        ->and($subscriptions[0]['id'])->toBeInt()
        ->and($subscriptions[0]['uri'])->toBe('holidays')
        ->and($subscriptions[0]['principaluri'])->toBe('principals/'.$owner->getKey())
        ->and($subscriptions[0]['source'])->toBe('https://example.com/holidays.ics')
        ->and($subscriptions[0]['{DAV:}displayname'])->toBe('Holidays')
        ->and($subscriptions[0]['{http://apple.com/ns/ical/}calendar-color'])->toBe('#B8255FFF')
        ->and($subscriptions[0]['{http://apple.com/ns/ical/}refreshrate'])->toBe('P1D')
        ->and($subscriptions[0]['{http://apple.com/ns/ical/}calendar-order'])->toBe(10)
        ->and($subscriptions[0]['{http://calendarserver.org/ns/}subscribed-strip-todos'])->toBeTrue()
        ->and($subscriptions[0]['{http://calendarserver.org/ns/}subscribed-strip-alarms'])->toBeTrue()
        ->and($subscriptions[0]['{http://calendarserver.org/ns/}subscribed-strip-attachments'])->toBeTrue()
        ->and($subscriptions[0]['{urn:ietf:params:xml:ns:caldav}supported-calendar-component-set'])->toBeInstanceOf(SupportedCalendarComponentSet::class);
});

it('creates, updates, and deletes subscriptions through the sabre backend', function (): void {
    $owner = OwnerUser::factory()->create();
    $principalUri = 'principals/'.$owner->getKey();
    $backend = subscriptionBackend();

    expect($backend)->toBeInstanceOf(SubscriptionSupport::class);

    $subscriptionId = $backend->createSubscription($principalUri, 'holidays', [
        '{http://calendarserver.org/ns/}source' => new Href('https://example.com/holidays.ics'),
        '{DAV:}displayname' => 'Holidays',
        '{http://apple.com/ns/ical/}calendar-color' => '#B8255FFF',
        '{http://apple.com/ns/ical/}refreshrate' => 'P1D',
        '{http://apple.com/ns/ical/}calendar-order' => '10',
        '{http://calendarserver.org/ns/}subscribed-strip-todos' => '',
    ]);

    $subscription = DavCalendarSubscription::query()->findOrFail($subscriptionId);

    expect($subscription)
        ->owner_id->toBe($owner->getKey())
        ->uri->toBe('holidays')
        ->source->toBe('https://example.com/holidays.ics')
        ->display_name->toBe('Holidays')
        ->color->toBe('#B8255FFF')
        ->refresh_rate->toBe('P1D')
        ->order->toBe(10)
        ->strip_todos->toBeTrue()
        ->strip_alarms->toBeFalse()
        ->strip_attachments->toBeFalse()
        ->last_modified_at->not->toBeNull();

    $patch = new PropPatch([
        '{http://calendarserver.org/ns/}source' => new Href('https://example.com/updated.ics'),
        '{DAV:}displayname' => 'Updated Holidays',
        '{http://apple.com/ns/ical/}calendar-color' => '#00AA00FF',
        '{http://apple.com/ns/ical/}refreshrate' => 'PT4H',
        '{http://apple.com/ns/ical/}calendar-order' => '20',
        '{http://calendarserver.org/ns/}subscribed-strip-alarms' => '',
        '{http://calendarserver.org/ns/}subscribed-strip-todos' => null,
    ]);

    $backend->updateSubscription($subscriptionId, $patch);

    expect($patch->commit())->toBeTrue();

    $subscription->refresh();

    expect($subscription)
        ->source->toBe('https://example.com/updated.ics')
        ->display_name->toBe('Updated Holidays')
        ->color->toBe('#00AA00FF')
        ->refresh_rate->toBe('PT4H')
        ->order->toBe(20)
        ->strip_todos->toBeFalse()
        ->strip_alarms->toBeTrue();

    DavCalendar::factory()->withInstance([
        'uri' => 'personal',
    ])->create([
        'owner_id' => $owner->getKey(),
    ]);

    $backend->deleteSubscription($subscriptionId);

    expect(DavCalendarSubscription::query()->whereKey($subscriptionId)->exists())->toBeFalse()
        ->and(DavCalendar::query()->where('owner_id', $owner->getKey())->exists())->toBeTrue();
});
