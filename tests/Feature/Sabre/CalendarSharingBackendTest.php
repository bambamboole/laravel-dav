<?php

use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Sabre\CalDAV\Backend\SharingSupport;
use Sabre\DAV\Exception\NotImplemented;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Sabre\DAV\Xml\Element\Sharee;

function sharingBackend(): CalendarBackend
{
    return app(CalendarBackend::class);
}

it('creates updates lists and removes calendar share invites', function (): void {
    $owner = davActor()['owner'];
    $sharee = davActor()['owner'];
    $calendar = DavCalendar::factory()->withInstance([
        'uri' => 'personal',
        'display_name' => 'Personal',
        'description' => 'Private calendar',
        'color' => '#3A87ADFF',
    ])->create([
        'owner_id' => $owner->getKey(),
    ]);
    $ownerInstance = $calendar->ownerInstance()->firstOrFail();
    $backend = sharingBackend();

    expect($backend)->toBeInstanceOf(SharingSupport::class);

    $backend->updateInvites([(int) $calendar->id, (int) $ownerInstance->id], [
        new Sharee([
            'href' => 'mailto:'.$sharee->email,
            'principal' => 'principals/'.$sharee->getKey(),
            'access' => SharingPlugin::ACCESS_READWRITE,
            'properties' => ['{DAV:}displayname' => 'Shared to '.$sharee->name],
        ]),
    ]);

    $shareeInstance = DavCalendarInstance::query()
        ->where('dav_calendar_id', $calendar->id)
        ->where('owner_id', $sharee->getKey())
        ->firstOrFail();

    expect($shareeInstance)
        ->access->toBe(SharingPlugin::ACCESS_READWRITE)
        ->display_name->toBe('Personal')
        ->description->toBe('Private calendar')
        ->color->toBe('#3A87ADFF')
        ->share_href->toBe('mailto:'.$sharee->email)
        ->share_display_name->toBe('Shared to '.$sharee->name)
        ->share_invite_status->toBe(SharingPlugin::INVITE_ACCEPTED);

    $invites = $backend->getInvites([(int) $calendar->id, (int) $ownerInstance->id]);

    expect($invites)->toHaveCount(2)
        ->and($invites[0])->toBeInstanceOf(Sharee::class)
        ->and(collect($invites)->first(fn (Sharee $invite): bool => $invite->principal === 'principals/'.$sharee->getKey()))
        ->access->toBe(SharingPlugin::ACCESS_READWRITE)
        ->inviteStatus->toBe(SharingPlugin::INVITE_ACCEPTED);

    $backend->updateInvites([(int) $calendar->id, (int) $ownerInstance->id], [
        new Sharee([
            'href' => 'mailto:'.$sharee->email,
            'principal' => 'principals/'.$sharee->getKey(),
            'access' => SharingPlugin::ACCESS_READ,
            'properties' => ['{DAV:}displayname' => 'Read only '.$sharee->name],
        ]),
    ]);

    expect($shareeInstance->refresh())
        ->access->toBe(SharingPlugin::ACCESS_READ)
        ->share_display_name->toBe('Read only '.$sharee->name);

    $backend->updateInvites([(int) $calendar->id, (int) $ownerInstance->id], [
        new Sharee([
            'href' => 'mailto:'.$sharee->email,
            'access' => SharingPlugin::ACCESS_NOACCESS,
        ]),
    ]);

    expect(DavCalendarInstance::query()->whereKey($shareeInstance->id)->exists())->toBeFalse()
        ->and(DavCalendar::query()->whereKey($calendar->id)->exists())->toBeTrue()
        ->and(DavCalendarInstance::query()->whereKey($ownerInstance->id)->exists())->toBeTrue();
});

it('rejects publishing calendars explicitly', function (): void {
    $calendar = DavCalendar::factory()->withInstance()->create();
    $ownerInstance = $calendar->ownerInstance()->firstOrFail();

    sharingBackend()->setPublishStatus([(int) $calendar->id, (int) $ownerInstance->id], true);
})->throws(NotImplemented::class);
