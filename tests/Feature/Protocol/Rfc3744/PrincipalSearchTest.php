<?php

use Bambamboole\LaravelDav\Models\DavCredential;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Bambamboole\LaravelDav\Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

/**
 * @return array{owner: OwnerUser, header: string}
 */
function principalSearchActor(string $name): array
{
    $owner = OwnerUser::factory()->create(['name' => $name]);
    $secret = 'super-secret-token';
    $username = 'dav-'.$owner->getKey();

    DavCredential::factory()->create([
        'user_id' => $owner->getKey(),
        'username' => $username,
        'secret_hash' => Hash::make($secret),
    ]);

    return [
        'owner' => $owner,
        'header' => davAuthHeader($username, $secret),
    ];
}

function principalPropertySearchReport(TestCase $test, string $authHeader, string $body): TestResponse
{
    return $test->callDav('REPORT', '/dav/', $authHeader, $body, [
        'HTTP_DEPTH' => '0',
    ]);
}

it('[section 9.4] finds a principal by name and returns its displayname and calendar-home-set', function (): void {
    $match = principalSearchActor('Ada Lovelace');
    $other = principalSearchActor('Grace Hopper');

    principalPropertySearchReport($this, $match['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:principal-property-search xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:property-search>
                <d:prop>
                    <d:displayname />
                </d:prop>
                <d:match>Ada Lovelace</d:match>
            </d:property-search>
            <d:prop>
                <d:displayname />
                <cal:calendar-home-set />
            </d:prop>
        </d:principal-property-search>
        XML)
        ->assertStatus(207)
        ->assertSee('/dav/principals/'.$match['owner']->getKey().'/', false)
        ->assertSee('Ada Lovelace', false)
        ->assertSee('/dav/calendars/'.$match['owner']->getKey().'/', false)
        ->assertDontSee('/dav/principals/'.$other['owner']->getKey().'/', false)
        ->assertDontSee('Grace Hopper', false);
});

it('[section 9.4] lists all principals for authenticated users', function (): void {
    $first = principalSearchActor('Ada Lovelace');
    $second = principalSearchActor('Grace Hopper');

    principalPropertySearchReport($this, $first['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:principal-property-search xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
            <d:prop>
                <d:displayname />
                <cal:calendar-home-set />
            </d:prop>
        </d:principal-property-search>
        XML)
        ->assertStatus(207)
        ->assertSee('/dav/principals/'.$first['owner']->getKey().'/', false)
        ->assertSee('/dav/principals/'.$second['owner']->getKey().'/', false)
        ->assertSee('Ada Lovelace', false)
        ->assertSee('Grace Hopper', false);
});

it('[section 9.4] finds a principal by name using the python caldav client query shape', function (): void {
    $match = principalSearchActor('Ada Lovelace');
    $other = principalSearchActor('Grace Hopper');

    principalPropertySearchReport($this, $match['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <D:principal-property-search xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav"><D:property-search><D:prop><D:displayname/></D:prop><D:match>Ada Lovelace</D:match></D:property-search><D:prop/><C:calendar-home-set/><D:displayname/></D:principal-property-search>
        XML)
        ->assertStatus(207)
        ->assertSee('/dav/principals/'.$match['owner']->getKey().'/', false)
        ->assertSee('Ada Lovelace', false)
        ->assertSee('/dav/calendars/'.$match['owner']->getKey().'/', false)
        ->assertDontSee('Grace Hopper', false);
});

it('[section 9.4] lists all principals using the python caldav client query shape', function (): void {
    $first = principalSearchActor('Ada Lovelace');
    $second = principalSearchActor('Grace Hopper');

    principalPropertySearchReport($this, $first['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <D:principal-property-search xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav"><D:prop/><C:calendar-home-set/><D:displayname/></D:principal-property-search>
        XML)
        ->assertStatus(207)
        ->assertSee('/dav/principals/'.$first['owner']->getKey().'/', false)
        ->assertSee('/dav/principals/'.$second['owner']->getKey().'/', false)
        ->assertSee('Ada Lovelace', false)
        ->assertSee('Grace Hopper', false)
        ->assertSee('/dav/calendars/'.$first['owner']->getKey().'/', false)
        ->assertSee('/dav/calendars/'.$second['owner']->getKey().'/', false);
});

it('[section 9.4] degrades gracefully when only unsupported search properties are requested', function (): void {
    $actor = principalSearchActor('Ada Lovelace');

    principalPropertySearchReport($this, $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:principal-property-search xmlns:d="DAV:">
            <d:property-search>
                <d:prop>
                    <d:getcontentlanguage />
                </d:prop>
                <d:match>anything</d:match>
            </d:property-search>
            <d:prop>
                <d:displayname />
            </d:prop>
        </d:principal-property-search>
        XML)
        ->assertStatus(207)
        ->assertDontSee('/dav/principals/'.$actor['owner']->getKey().'/', false);
});
