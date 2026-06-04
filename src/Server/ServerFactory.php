<?php

namespace Bambamboole\LaravelDav\Server;

use Bambamboole\LaravelDav\Sabre\Auth\BasicAuthBackend;
use Bambamboole\LaravelDav\Sabre\CalDav\CalendarBackend;
use Bambamboole\LaravelDav\Sabre\CalDav\Xml\Request\CalendarQueryReport;
use Bambamboole\LaravelDav\Sabre\CardDav\AddressBookBackend;
use Bambamboole\LaravelDav\Sabre\Principal\PrincipalBackend;
use Bambamboole\LaravelDav\Sabre\PropertyStorage\PropertyBackend;
use Sabre\CalDAV\CalendarRoot;
use Sabre\CalDAV\ICSExportPlugin;
use Sabre\CalDAV\Plugin as CalDavPlugin;
use Sabre\CardDAV\AddressBookRoot;
use Sabre\CardDAV\Plugin as CardDavPlugin;
use Sabre\CardDAV\VCFExportPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\PropertyStorage\Plugin as PropertyStoragePlugin;
use Sabre\DAV\Server;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\DAVACL\PrincipalCollection;

class ServerFactory
{
    public function __construct(
        private readonly BasicAuthBackend $authBackend,
        private readonly PrincipalBackend $principalBackend,
        private readonly CalendarBackend $calendarBackend,
        private readonly AddressBookBackend $addressBookBackend,
        private readonly PropertyBackend $propertyBackend,
    ) {}

    public function make(): Server
    {
        $server = new Server([
            new PrincipalCollection($this->principalBackend),
            new CalendarRoot($this->principalBackend, $this->calendarBackend),
            new AddressBookRoot($this->principalBackend, $this->addressBookBackend),
        ]);

        $server->setBaseUri((string) config('dav.base_uri'));
        $server->addPlugin(new AuthPlugin($this->authBackend));
        $server->addPlugin(new PropertyStoragePlugin($this->propertyBackend));
        $server->addPlugin(new AclPlugin);
        $server->addPlugin(new CalDavPlugin);
        $server->xml->elementMap['{urn:ietf:params:xml:ns:caldav}calendar-query'] = CalendarQueryReport::class;
        $server->addPlugin(new CardDavPlugin);
        $server->addPlugin(new SyncPlugin);
        $server->addPlugin(new ICSExportPlugin);
        $server->addPlugin(new VCFExportPlugin);

        return $server;
    }
}
