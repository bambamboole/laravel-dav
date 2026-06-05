<?php

namespace Bambamboole\LaravelDav\Server;

use Bambamboole\LaravelDav\Sabre\Auth\BasicAuthBackend;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Sabre\CalDav\ExpandingVCalendar;
use Bambamboole\LaravelDav\Sabre\CalDav\Xml\Request\CalendarQueryReport;
use Bambamboole\LaravelDav\Sabre\CardDav\AddressBookBackend;
use Bambamboole\LaravelDav\Sabre\DAVACL\EnumerablePrincipalCollection;
use Bambamboole\LaravelDav\Sabre\DAVACL\Xml\Request\PrincipalPropertySearchReport;
use Bambamboole\LaravelDav\Sabre\Locks\LockBackend;
use Bambamboole\LaravelDav\Sabre\Principal\PrincipalBackend;
use Bambamboole\LaravelDav\Sabre\PropertyStorage\PropertyBackend;
use Bambamboole\LaravelDav\Sabre\Schedule\IMipPlugin;
use Bambamboole\LaravelDav\Sabre\Schedule\ScheduleTagPlugin;
use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\ICSExportPlugin;
use Sabre\CalDAV\Plugin as CalDavPlugin;
use Sabre\CalDAV\Schedule\Plugin as SchedulePlugin;
use Sabre\CalDAV\SharingPlugin as CalDavSharingPlugin;
use Sabre\CalDAV\Subscriptions\Plugin as SubscriptionsPlugin;
use Sabre\CardDAV\AddressBookRoot;
use Sabre\CardDAV\Plugin as CardDavPlugin;
use Sabre\CardDAV\VCFExportPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\Locks\Plugin as LocksPlugin;
use Sabre\DAV\PropertyStorage\Plugin as PropertyStoragePlugin;
use Sabre\DAV\Server;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\VObject\Component\VCalendar;

class ServerFactory
{
    public function __construct(
        private readonly BasicAuthBackend $authBackend,
        private readonly PrincipalBackend $principalBackend,
        private readonly CalendarBackend $calendarBackend,
        private readonly AddressBookBackend $addressBookBackend,
        private readonly PropertyBackend $propertyBackend,
        private readonly LockBackend $lockBackend,
    ) {}

    public function make(): Server
    {
        VCalendar::$componentMap['VCALENDAR'] = ExpandingVCalendar::class;

        $server = new Server([
            new EnumerablePrincipalCollection($this->principalBackend),
            new CalendarRoot($this->principalBackend, $this->calendarBackend),
            new AddressBookRoot($this->principalBackend, $this->addressBookBackend),
        ]);

        $server->setBaseUri($this->baseUri());
        $server->debugExceptions = (bool) config('app.debug');
        $server->addPlugin(new AuthPlugin($this->authBackend));
        $server->addPlugin(new PropertyStoragePlugin($this->propertyBackend));
        $server->addPlugin(new LocksPlugin($this->lockBackend));
        $server->addPlugin(new AclPlugin);
        $server->addPlugin(new CalDavPlugin);
        $server->addPlugin(new SharingPlugin);
        $server->addPlugin(new CalDavSharingPlugin);
        $server->addPlugin(new SubscriptionsPlugin);
        $server->xml->elementMap['{urn:ietf:params:xml:ns:caldav}calendar-query'] = CalendarQueryReport::class;
        $server->xml->elementMap['{DAV:}principal-property-search'] = PrincipalPropertySearchReport::class;
        if (config('dav.scheduling.enabled')) {
            $server->addPlugin(new SchedulePlugin);
            $server->addPlugin(new ScheduleTagPlugin);
            $server->addPlugin(new IMipPlugin(config('dav.scheduling.from')));
        }
        $server->addPlugin(new CardDavPlugin);
        $server->addPlugin(new SyncPlugin);
        $server->addPlugin(new ICSExportPlugin);
        $server->addPlugin(new VCFExportPlugin);

        return $server;
    }

    private function baseUri(): string
    {
        $configuredBaseUri = config('dav.base_uri');

        if (is_string($configuredBaseUri) && $configuredBaseUri !== '') {
            return $configuredBaseUri;
        }

        $prefix = trim((string) config('dav.route.prefix', 'dav'), '/');

        return $prefix === '' ? '/' : "/{$prefix}/";
    }
}
