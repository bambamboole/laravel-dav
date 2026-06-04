<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav\Xml\Request;

use Bambamboole\LaravelDav\Sabre\CalDav\Xml\Filter\CompFilter;
use Sabre\CalDAV\Plugin;
use Sabre\DAV\Exception\BadRequest;
use Sabre\Xml\Reader;
use Sabre\Xml\XmlDeserializable;

class CalendarQueryReport implements XmlDeserializable
{
    /** @var array<int, string> */
    public array $properties = [];

    /** @var array<string, mixed> */
    public array $filters = [];

    /** @var array{start: \DateTimeInterface|null, end: \DateTimeInterface|null}|null */
    public ?array $expand = null;

    public ?string $contentType = null;

    public ?string $version = null;

    public static function xmlDeserialize(Reader $reader): self
    {
        $elements = $reader->parseInnerTree([
            '{'.Plugin::NS_CALDAV.'}comp-filter' => CompFilter::class,
            '{'.Plugin::NS_CALDAV.'}prop-filter' => 'Sabre\\CalDAV\\Xml\\Filter\\PropFilter',
            '{'.Plugin::NS_CALDAV.'}param-filter' => 'Sabre\\CalDAV\\Xml\\Filter\\ParamFilter',
            '{'.Plugin::NS_CALDAV.'}calendar-data' => 'Sabre\\CalDAV\\Xml\\Filter\\CalendarData',
            '{DAV:}prop' => 'Sabre\\Xml\\Element\\KeyValue',
        ]);

        $properties = [];
        $filters = null;
        $calendarDataOptions = [];

        if (! is_array($elements)) {
            $elements = [];
        }

        foreach ($elements as $element) {
            switch ($element['name']) {
                case '{DAV:}prop':
                    $properties = array_keys($element['value']);

                    if (isset($element['value']['{'.Plugin::NS_CALDAV.'}calendar-data'])) {
                        $calendarDataOptions = $element['value']['{'.Plugin::NS_CALDAV.'}calendar-data'];
                    }
                    break;
                case '{'.Plugin::NS_CALDAV.'}filter':
                    foreach ($element['value'] as $subElement) {
                        if ($subElement['name'] !== '{'.Plugin::NS_CALDAV.'}comp-filter') {
                            continue;
                        }

                        if ($filters !== null) {
                            throw new BadRequest('Only one top-level comp-filter may be defined');
                        }

                        $filters = $subElement['value'];
                    }
                    break;
            }
        }

        if ($filters === null) {
            throw new BadRequest('The {'.Plugin::NS_CALDAV.'}filter element is required for this request');
        }

        $report = new self;
        $report->properties = $properties;
        $report->filters = $filters;

        foreach ($calendarDataOptions as $key => $value) {
            $report->{$key} = $value;
        }

        return $report;
    }
}
