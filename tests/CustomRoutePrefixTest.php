<?php

use Bambamboole\LaravelDav\Tests\TestCase;

class CustomRoutePrefixTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('dav.route.prefix', 'remote.php/dav');
    }
}

uses(CustomRoutePrefixTestCase::class);

it('uses the configured route prefix for discovery and advertised hrefs', function (): void {
    $actor = davActor();
    $ownerId = $actor['owner']->getKey();

    $this->callDav('PROPFIND', '/.well-known/caldav')
        ->assertRedirect('/remote.php/dav/');

    $this->callDav('PROPFIND', '/remote.php/dav/principals/'.$ownerId.'/', $actor['header'], <<<'XML'
        <?xml version="1.0" encoding="utf-8" ?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:principal-URL />
            </d:prop>
        </d:propfind>
        XML, [
        'HTTP_DEPTH' => '0',
    ])
        ->assertStatus(207)
        ->assertSee('/remote.php/dav/principals/'.$ownerId.'/', false);
});
