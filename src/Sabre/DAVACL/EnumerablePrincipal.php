<?php

namespace Bambamboole\LaravelDav\Sabre\DAVACL;

use Sabre\CalDAV\Principal\User;

/**
 * A principal node that, in addition to granting all privileges to its owner,
 * grants read access to every authenticated principal.
 *
 * This implements the package policy that any authenticated DAV user may
 * search and enumerate all principals (and read their public properties such
 * as displayname and calendar-home-set).
 */
class EnumerablePrincipal extends User
{
    /**
     * @return array<int, array{privilege: string, principal: string, protected: bool}>
     */
    public function getACL(): array
    {
        return [
            [
                'privilege' => '{DAV:}all',
                'principal' => '{DAV:}owner',
                'protected' => true,
            ],
            [
                'privilege' => '{DAV:}read',
                'principal' => '{DAV:}authenticated',
                'protected' => true,
            ],
        ];
    }
}
