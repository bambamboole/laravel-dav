<?php

namespace Bambamboole\LaravelDav\Server;

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Sabre\Auth\BasicAuthBackend;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Sabre\CalDav\ExpandingVCalendar;
use Bambamboole\LaravelDav\Sabre\CalDav\ManagedAttachmentsPlugin;
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
use Sabre\DAV\INode;
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

    public function create(): Server
    {
        VCalendar::$componentMap['VCALENDAR'] = ExpandingVCalendar::class;

        $server = $this->createServer($this->getNodes());

        if ($this->calDavEnabled()) {
            $server->addPlugin(new CalDavPlugin);
            $server->addPlugin(new ICSExportPlugin);
            $server->addPlugin(new ManagedAttachmentsPlugin($this->calendarBackend));
            $server->addPlugin(new SharingPlugin);
            $server->addPlugin(new CalDavSharingPlugin);
            $server->addPlugin(new SubscriptionsPlugin);
            $server->xml->elementMap['{urn:ietf:params:xml:ns:caldav}calendar-query'] = CalendarQueryReport::class;

            if ($this->schedulingEnabled()) {
                $server->addPlugin(new SchedulePlugin);
                $server->addPlugin(new ScheduleTagPlugin);
                $server->addPlugin(new IMipPlugin($this->schedulingFrom()));
            }
        }

        if ($this->cardDavEnabled()) {
            $server->addPlugin(new CardDavPlugin);
            $server->addPlugin(new VCFExportPlugin);
        }

        return $this->prepareServer($server);
    }

    protected function prepareServer(Server $server): Server
    {
        return $server;
    }

    /**
     * @param  array<int, INode>  $nodes
     */
    protected function createServer(array $nodes): Server
    {
        $server = new Server($nodes);
        $server->setBaseUri($this->baseUri());
        $server->debugExceptions = $this->debugExceptionsEnabled();
        $server->addPlugin(new AuthPlugin($this->authBackend));
        $server->addPlugin(new PropertyStoragePlugin($this->propertyBackend));
        $server->addPlugin(new LocksPlugin($this->lockBackend));
        $server->addPlugin(new AclPlugin);
        $server->addPlugin(new SyncPlugin);
        $server->xml->elementMap['{DAV:}principal-property-search'] = PrincipalPropertySearchReport::class;

        return $server;
    }

    protected function baseUri(): string
    {
        return Dav::baseUri();
    }

    protected function debugExceptionsEnabled(): bool
    {
        return config()->boolean('app.debug');
    }

    protected function calDavEnabled(): bool
    {
        return config()->boolean('dav.caldav.enabled', true);
    }

    protected function cardDavEnabled(): bool
    {
        return config()->boolean('dav.carddav.enabled', true);
    }

    protected function schedulingEnabled(): bool
    {
        return config()->boolean('dav.scheduling.enabled');
    }

    protected function schedulingFrom(): ?string
    {
        return config()->string('dav.scheduling.from', 'noreply@laravel-dav.example');
    }

    /**
     * @return array<int, INode>
     */
    protected function getNodes(): array
    {
        $nodes = [
            new EnumerablePrincipalCollection($this->principalBackend),
        ];

        if ($this->calDavEnabled()) {
            $nodes[] = new CalendarRoot($this->principalBackend, $this->calendarBackend);
        }

        if ($this->cardDavEnabled()) {
            $nodes[] = new AddressBookRoot($this->principalBackend, $this->addressBookBackend);
        }

        return $nodes;
    }
}
