<?php

namespace Bambamboole\LaravelDav\Sabre\DAVACL;

use Sabre\DAV\INode;
use Sabre\DAVACL\PrincipalCollection;

/**
 * A principal collection whose child principals are readable by every
 * authenticated user, implementing the package's principal enumeration policy.
 */
class EnumerablePrincipalCollection extends PrincipalCollection
{
    /**
     * @param  array<string, mixed>  $principal
     */
    public function getChildForPrincipal(array $principal): INode
    {
        return new EnumerablePrincipal($this->principalBackend, $principal);
    }
}
