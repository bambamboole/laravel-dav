<?php

use Bambamboole\LaravelDav\Sabre\PropertyStorage\PropertyBackend;
use Illuminate\Support\Facades\DB;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;

it('round-trips a property through propPatch and propFind', function (): void {
    $backend = new PropertyBackend;

    $patch = new PropPatch(['{DAV:}displayname' => 'My Calendar']);
    $backend->propPatch('calendars/work', $patch);
    $patch->commit();

    $find = new PropFind('calendars/work', ['{DAV:}displayname']);
    $backend->propFind('calendars/work', $find);

    expect($find->get('{DAV:}displayname'))->toBe('My Calendar');
});

it('removes a property when it is set to null', function (): void {
    $backend = new PropertyBackend;

    $create = new PropPatch(['{DAV:}displayname' => 'Original']);
    $backend->propPatch('calendars/work', $create);
    $create->commit();

    $remove = new PropPatch(['{DAV:}displayname' => null]);
    $backend->propPatch('calendars/work', $remove);
    $remove->commit();

    expect(DB::table('dav_properties')->where('path', 'calendars/work')->count())->toBe(0);

    $find = new PropFind('calendars/work', ['{DAV:}displayname']);
    $backend->propFind('calendars/work', $find);

    expect($find->get('{DAV:}displayname'))->toBeNull();
});

it('deletes all properties under a path', function (): void {
    $backend = new PropertyBackend;

    $patch = new PropPatch(['{DAV:}displayname' => 'Calendar']);
    $backend->propPatch('calendars/work', $patch);
    $patch->commit();

    $backend->delete('calendars/work');

    expect(DB::table('dav_properties')->where('path', 'calendars/work')->count())->toBe(0);
});
