<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav\Xml\Filter;

use Sabre\CalDAV\Plugin;
use Sabre\DAV\Exception\BadRequest;
use Sabre\VObject\DateTimeParser;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

class CompFilter implements XmlDeserializable
{
    /**
     * @return array{name: string|null, is-not-defined: bool, comp-filters: array<int, mixed>, prop-filters: array<int, mixed>, time-range: array{start?: \DateTimeInterface|null, end?: \DateTimeInterface|null}|false}
     */
    public static function xmlDeserialize(Reader $reader): array
    {
        $result = [
            'name' => null,
            'is-not-defined' => false,
            'comp-filters' => [],
            'prop-filters' => [],
            'time-range' => false,
        ];

        $attributes = $reader->parseAttributes();
        $result['name'] = $attributes['name'] ?? null;

        $elements = $reader->parseInnerTree([
            '{'.Plugin::NS_CALDAV.'}comp-filter' => self::class,
            '{'.Plugin::NS_CALDAV.'}prop-filter' => 'Sabre\\CalDAV\\Xml\\Filter\\PropFilter',
            '{'.Plugin::NS_CALDAV.'}param-filter' => 'Sabre\\CalDAV\\Xml\\Filter\\ParamFilter',
        ]);

        if (! is_array($elements)) {
            return $result;
        }

        foreach ($elements as $element) {
            switch ($element['name']) {
                case '{'.Plugin::NS_CALDAV.'}comp-filter':
                    $result['comp-filters'][] = $element['value'];
                    break;
                case '{'.Plugin::NS_CALDAV.'}prop-filter':
                    $result['prop-filters'][] = $element['value'];
                    break;
                case '{'.Plugin::NS_CALDAV.'}is-not-defined':
                    $result['is-not-defined'] = true;
                    break;
                case '{'.Plugin::NS_CALDAV.'}time-range':
                    $result['time-range'] = [
                        'start' => isset($element['attributes']['start'])
                            ? DateTimeParser::parseDateTime($element['attributes']['start'])
                            : null,
                        'end' => isset($element['attributes']['end'])
                            ? DateTimeParser::parseDateTime($element['attributes']['end'])
                            : null,
                    ];

                    if ($result['time-range']['start'] && $result['time-range']['end'] && $result['time-range']['end'] <= $result['time-range']['start']) {
                        throw new BadRequest('The end-date must be larger than the start-date');
                    }
                    break;
            }
        }

        return $result;
    }
}
