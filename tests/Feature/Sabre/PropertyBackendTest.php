<?php

use Bambamboole\LaravelDav\Sabre\PropertyStorage\PropertyBackend;
use Illuminate\Support\Facades\DB;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Xml\Property\Complex;
use Sabre\DAV\Xml\Property\Href;

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

it('round-trips an xml property through propPatch and propFind', function (): void {
    $backend = new PropertyBackend;
    $value = new Complex('<x:flag xmlns:x="http://LaravelDav.test/ns">enabled</x:flag>');

    $patch = new PropPatch(['{http://LaravelDav.test/ns}custom-xml' => $value]);
    $backend->propPatch('calendars/work', $patch);
    $patch->commit();

    $find = new PropFind('calendars/work', ['{http://LaravelDav.test/ns}custom-xml']);
    $backend->propFind('calendars/work', $find);

    $stored = $find->get('{http://LaravelDav.test/ns}custom-xml');

    expect($stored)
        ->toBeInstanceOf(Complex::class)
        ->and($stored->getXml())->toBe('<x:flag xmlns:x="http://LaravelDav.test/ns">enabled</x:flag>')
        ->and(DB::table('dav_properties')->where('path', 'calendars/work')->value('value_type'))->toBe('xml');
});

it('round-trips an object property through propPatch and propFind', function (): void {
    $backend = new PropertyBackend;

    $patch = new PropPatch(['{DAV:}owner' => new Href('/principals/1/')]);
    $backend->propPatch('calendars/work', $patch);
    $patch->commit();

    $find = new PropFind('calendars/work', ['{DAV:}owner']);
    $backend->propFind('calendars/work', $find);

    $stored = $find->get('{DAV:}owner');

    expect($stored)
        ->toBeInstanceOf(Href::class)
        ->and($stored->getHref())->toBe('/principals/1/')
        ->and(DB::table('dav_properties')->where('path', 'calendars/work')->value('value_type'))->toBe('object');
});

it('deletes all properties under a path', function (): void {
    $backend = new PropertyBackend;

    $patch = new PropPatch(['{DAV:}displayname' => 'Calendar']);
    $backend->propPatch('calendars/work', $patch);
    $patch->commit();

    $backend->delete('calendars/work');

    expect(DB::table('dav_properties')->where('path', 'calendars/work')->count())->toBe(0);
});

it('moves all properties under a path', function (): void {
    $backend = new PropertyBackend;

    $patch = new PropPatch(['{DAV:}displayname' => 'Event']);
    $backend->propPatch('calendars/work/events/1.ics', $patch);
    $patch->commit();

    $backend->move('calendars/work', 'calendars/archive');

    expect(DB::table('dav_properties')->where('path', 'calendars/work/events/1.ics')->count())->toBe(0)
        ->and(DB::table('dav_properties')->where('path', 'calendars/archive/events/1.ics')->count())->toBe(1);
});
