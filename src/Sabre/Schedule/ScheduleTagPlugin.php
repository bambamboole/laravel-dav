<?php

namespace Bambamboole\LaravelDav\Sabre\Schedule;

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Illuminate\Database\Eloquent\Builder;
use Sabre\CalDAV\ICalendarObject;
use Sabre\DAV\Exception\PreconditionFailed;
use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class ScheduleTagPlugin extends ServerPlugin
{
    private const ScheduleTagProperty = '{urn:ietf:params:xml:ns:caldav}schedule-tag';

    private Server $server;

    public function initialize(Server $server): void
    {
        $this->server = $server;
        $this->server->protectedProperties[] = self::ScheduleTagProperty;
        $this->server->on('beforeMethod:PUT', [$this, 'guardScheduleTag'], 90);
        $this->server->on('afterMethod:GET', [$this, 'addScheduleTagHeader']);
        $this->server->on('afterMethod:PUT', [$this, 'addScheduleTagHeader']);
        $this->server->on('propFind', [$this, 'propFind']);
    }

    public function guardScheduleTag(RequestInterface $request, ResponseInterface $response): void
    {
        $expected = $request->getHeader('If-Schedule-Tag-Match');

        if ($expected === null) {
            return;
        }

        $object = $this->calendarObjectForPath($request->getPath());

        if ($object === null || $this->quotedScheduleTag($object) !== trim($expected)) {
            throw new PreconditionFailed(
                'An If-Schedule-Tag-Match header was specified, but it did not match the current schedule tag.',
                'If-Schedule-Tag-Match',
            );
        }
    }

    public function addScheduleTagHeader(RequestInterface $request, ResponseInterface $response): void
    {
        $scheduleTag = $this->quotedScheduleTag($this->calendarObjectForPath($request->getPath()));

        if ($scheduleTag !== null) {
            $response->setHeader('Schedule-Tag', $scheduleTag);
        }
    }

    public function propFind(PropFind $propFind, INode $node): void
    {
        if (! $node instanceof ICalendarObject) {
            return;
        }

        $propFind->handle(self::ScheduleTagProperty, function () use ($propFind): ?string {
            return $this->quotedScheduleTag($this->calendarObjectForPath($propFind->getPath()));
        });
    }

    public function getPluginName(): string
    {
        return 'schedule-tag';
    }

    private function quotedScheduleTag(?DavCalendarObject $object): ?string
    {
        if ($object === null || $object->schedule_tag === null || $object->schedule_tag === '') {
            return null;
        }

        return '"'.$object->schedule_tag.'"';
    }

    private function calendarObjectForPath(string $path): ?DavCalendarObject
    {
        $parts = explode('/', trim($path, '/'), 4);

        if (count($parts) !== 4 || $parts[0] !== 'calendars') {
            return null;
        }

        [, $ownerId, $calendarUri, $objectUri] = $parts;

        return Dav::modelFor('calendar_object', DavCalendarObject::class)::query()
            ->where('uri', $objectUri)
            ->whereHas('calendar', function (Builder $query) use ($ownerId, $calendarUri): void {
                $query->where('user_id', $ownerId)->where('uri', $calendarUri);
            })
            ->first();
    }
}
