<?php

namespace Bambamboole\LaravelDav\Sabre\DAVACL\Xml\Request;

use Sabre\DAV\Exception\BadRequest;
use Sabre\DAVACL\Plugin;
use Sabre\DAVACL\Xml\Request\PrincipalPropertySearchReport as SabrePrincipalPropertySearchReport;
use Sabre\Xml\Reader;

/**
 * A graceful, enumeration-friendly variant of Sabre's
 * {@see SabrePrincipalPropertySearchReport}.
 *
 * It differs in three ways:
 *  1. A report without any {DAV:}property-search element is treated as a
 *     match-all enumeration instead of a {@see BadRequest}.
 *  2. The search is always applied to the principal collection set, so a
 *     report sent to the DAV root resolves against the principal collection.
 *  3. Requested properties are also collected from elements placed directly
 *     under {DAV:}principal-property-search (siblings of {DAV:}prop) so the
 *     python caldav client's malformed query still yields displayname and
 *     calendar-home-set on each returned principal.
 *
 * It extends Sabre's report so it satisfies the type hint on
 * {@see Plugin::principalPropertySearchReport()}.
 */
class PrincipalPropertySearchReport extends SabrePrincipalPropertySearchReport
{
    public static function xmlDeserialize(Reader $reader): self
    {
        $self = new self;

        $self->searchProperties = [];
        $self->properties = [];
        $self->applyToPrincipalCollectionSet = true;
        $self->test = 'allof';
        if ($reader->getAttribute('test') === 'anyof') {
            $self->test = 'anyof';
        }

        $elemMap = [
            '{DAV:}property-search' => 'Sabre\\Xml\\Element\\KeyValue',
            '{DAV:}prop' => 'Sabre\\Xml\\Element\\KeyValue',
        ];

        $properties = [];

        foreach ($reader->parseInnerTree($elemMap) as $elem) {
            switch ($elem['name']) {
                case '{DAV:}prop':
                    foreach (array_keys($elem['value']) as $propName) {
                        $properties[$propName] = true;
                    }
                    break;
                case '{DAV:}property-search':
                    if (! isset($elem['value']['{DAV:}prop']) || ! isset($elem['value']['{DAV:}match'])) {
                        throw new BadRequest('The {DAV:}property-search element must contain one {DAV:}match and one {DAV:}prop element');
                    }
                    foreach ($elem['value']['{DAV:}prop'] as $propName => $discard) {
                        $self->searchProperties[$propName] = $elem['value']['{DAV:}match'];
                    }
                    break;
                case '{DAV:}apply-to-principal-collection-set':
                    break;
                default:
                    $properties[$elem['name']] = true;
                    break;
            }
        }

        $self->properties = array_keys($properties);

        return $self;
    }
}
