<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Exceptions\StaleDavResourceException;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Parsing\CalendarObjectSerializer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CalendarObjectWriter
{
    public function __construct(
        private CalendarObjectSerializer $serializer,
        private DavChangeRecorder $changeRecorder,
    ) {}

    public function create(DavCalendar $calendar, CalendarObjectData $data): DavCalendarObject
    {
        return DB::transaction(function () use ($calendar, $data): DavCalendarObject {
            $uid = $data->uid ?: (string) Str::uuid();
            $uri = $data->uri !== '' ? $data->uri : $uid.'.ics';
            $data = DtoFactory::calendarObjectData($data, [
                'uri' => $uri,
                'uid' => $uid,
                'componentType' => $data->componentType ?: 'VEVENT',
                'timezone' => $data->timezone ?: $calendar->timezone,
            ]);
            $payload = $this->serializer->serialize($data);

            $object = DavCalendarObject::createFromData($calendar, $uri, $data, $payload);

            $this->changeRecorder->recordCalendarChange($calendar, $object->uri, DavChangeOperation::Add);

            return $object->refresh();
        });
    }

    public function update(DavCalendarObject $object, CalendarObjectData $data, string $expectedEtag): DavCalendarObject
    {
        return DB::transaction(function () use ($object, $data, $expectedEtag): DavCalendarObject {
            $fresh = $object->newQuery()->whereKey($object->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->etag !== $expectedEtag) {
                throw new StaleDavResourceException($expectedEtag, $fresh->etag, $fresh->uri);
            }

            $data = DtoFactory::calendarObjectData($data, [
                'uri' => $fresh->uri,
                'uid' => $data->uid ?: $fresh->uid ?: (string) Str::uuid(),
                'componentType' => $data->componentType ?: 'VEVENT',
                'timezone' => $data->timezone ?: $fresh->timezone,
            ]);
            $payload = $this->serializer->merge($fresh->calendar_data, $data);

            $fresh->updateFromData($data, $payload);

            $calendar = $fresh->calendar()->firstOrFail();
            $this->changeRecorder->recordCalendarChange($calendar, $fresh->uri, DavChangeOperation::Modify);

            return $fresh->refresh();
        });
    }

    public function delete(DavCalendarObject $object, string $expectedEtag): void
    {
        DB::transaction(function () use ($object, $expectedEtag): void {
            $fresh = $object->newQuery()->whereKey($object->getKey())->lockForUpdate()->firstOrFail();

            if ($fresh->etag !== $expectedEtag) {
                throw new StaleDavResourceException($expectedEtag, $fresh->etag, $fresh->uri);
            }

            $calendar = $fresh->calendar()->firstOrFail();
            $uri = $fresh->uri;

            $fresh->delete();

            $this->changeRecorder->recordCalendarChange($calendar, $uri, DavChangeOperation::Delete);
        });
    }
}
