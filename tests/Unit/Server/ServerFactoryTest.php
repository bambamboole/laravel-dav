<?php

use Bambamboole\LaravelDav\Sabre\Auth\BasicAuthBackend;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Sabre\CalDav\ManagedAttachmentsPlugin;
use Bambamboole\LaravelDav\Sabre\CardDav\AddressBookBackend;
use Bambamboole\LaravelDav\Sabre\Locks\LockBackend;
use Bambamboole\LaravelDav\Sabre\Principal\PrincipalBackend;
use Bambamboole\LaravelDav\Sabre\PropertyStorage\PropertyBackend;
use Bambamboole\LaravelDav\Server\ServerFactory;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;

/**
 * @return array<int, string>
 */
function serverPluginNames(): array
{
    return array_keys(app(ServerFactory::class)->create()->getPlugins());
}

/**
 * @param  array<int, string>  $pluginNames
 */
function expectServerPluginsToContain(array $pluginNames, string ...$expectedPluginNames): void
{
    foreach ($expectedPluginNames as $expectedPluginName) {
        expect($pluginNames)->toContain($expectedPluginName);
    }
}

/**
 * @param  array<int, string>  $pluginNames
 */
function expectServerPluginsNotToContain(array $pluginNames, string ...$unexpectedPluginNames): void
{
    foreach ($unexpectedPluginNames as $unexpectedPluginName) {
        expect($pluginNames)->not->toContain($unexpectedPluginName);
    }
}

it('enables CalDAV and CardDAV server plugins by default', function (): void {
    expectServerPluginsToContain(
        serverPluginNames(),
        'caldav',
        ManagedAttachmentsPlugin::class,
        'sharing',
        'caldav-sharing',
        'subscriptions',
        'caldav-schedule',
        'schedule-tag',
        'imip',
        'carddav',
        'ics-export',
        'vcf-export',
    );
});

it('can disable CalDAV server plugins', function (): void {
    config()->set('dav.caldav.enabled', false);

    $pluginNames = serverPluginNames();

    expectServerPluginsNotToContain(
        $pluginNames,
        'caldav',
        ManagedAttachmentsPlugin::class,
        'sharing',
        'caldav-sharing',
        'subscriptions',
        'caldav-schedule',
        'schedule-tag',
        'imip',
        'ics-export',
    );

    expectServerPluginsToContain($pluginNames, 'carddav', 'vcf-export');
});

it('can disable CardDAV server plugins', function (): void {
    config()->set('dav.carddav.enabled', false);

    $pluginNames = serverPluginNames();

    expectServerPluginsNotToContain($pluginNames, 'carddav', 'vcf-export');

    expectServerPluginsToContain(
        $pluginNames,
        'caldav',
        ManagedAttachmentsPlugin::class,
        'sharing',
        'caldav-sharing',
        'subscriptions',
        'caldav-schedule',
        'schedule-tag',
        'imip',
        'ics-export',
    );
});

it('allows a bound factory subclass to override protected server preparation and config hooks', function (): void {
    app()->bind(ServerFactory::class, fn (): ServerFactory => new class(app(BasicAuthBackend::class), app(PrincipalBackend::class), app(CalendarBackend::class), app(AddressBookBackend::class), app(PropertyBackend::class), app(LockBackend::class)) extends ServerFactory
    {
        protected function prepareServer(Server $server): Server
        {
            $server->addPlugin(new class extends ServerPlugin
            {
                public function initialize(Server $server): void {}

                public function getPluginName(): string
                {
                    return 'custom-test-plugin';
                }
            });

            return $server;
        }

        protected function calDavEnabled(): bool
        {
            return false;
        }

        protected function cardDavEnabled(): bool
        {
            return false;
        }
    });

    $pluginNames = serverPluginNames();

    expectServerPluginsToContain($pluginNames, 'custom-test-plugin', 'sync');
    expectServerPluginsNotToContain($pluginNames, 'caldav', 'carddav', 'ics-export', 'vcf-export');
});
