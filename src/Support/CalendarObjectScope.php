<?php

namespace Bambamboole\LaravelDav\Support;

use BadMethodCallException;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CalendarObjectScope
{
    public function __construct(
        private CalendarObjectWriter $writer,
        private int|string $ownerId,
        private ?DavCalendar $calendar = null,
    ) {}

    /**
     * @return Collection<int, CalendarObjectData>
     */
    public function get(): Collection
    {
        return $this->query()
            ->get()
            ->map(fn (DavCalendarObject $object): CalendarObjectData => $object->toData());
    }

    public function find(int|string $id): ?CalendarObjectData
    {
        return $this->resourceQuery($id)->first()?->toData();
    }

    public function findOrFail(int|string $id): CalendarObjectData
    {
        return $this->resourceQuery($id)->firstOrFail()->toData();
    }

    public function create(CalendarObjectData $data): DavCalendarObject
    {
        if ($this->calendar === null) {
            throw new BadMethodCallException('Calendar objects can only be created through a calendar scope.');
        }

        return $this->writer->create($this->calendar, $data);
    }

    public function update(DavCalendarObject|int|string $object, CalendarObjectData $data, string $expectedEtag): DavCalendarObject
    {
        return $this->writer->update($this->model($object), $data, $expectedEtag);
    }

    public function delete(DavCalendarObject|int|string $object, string $expectedEtag): void
    {
        $this->writer->delete($this->model($object), $expectedEtag);
    }

    /**
     * @return Builder<DavCalendarObject>
     */
    private function query(): Builder
    {
        if ($this->calendar !== null) {
            return $this->calendar->objects()->getQuery()->orderBy('id');
        }

        /** @var class-string<DavCalendarObject> $model */
        $model = Dav::modelFor('calendar_object', DavCalendarObject::class);

        return $model::query()
            ->whereHas('calendar', fn (Builder $query): Builder => $query->where('user_id', $this->ownerId))
            ->orderBy('id');
    }

    /**
     * @return Builder<DavCalendarObject>
     */
    private function resourceQuery(int|string $id): Builder
    {
        return $this->query()
            ->where(function (Builder $query) use ($id): void {
                if (is_numeric($id)) {
                    $query->whereKey($id);
                }

                $query->orWhere('uri', $id);
            });
    }

    private function model(DavCalendarObject|int|string $object): DavCalendarObject
    {
        return $object instanceof DavCalendarObject
            ? $this->resourceQuery((string) $object->getKey())->firstOrFail()
            : $this->resourceQuery($object)->firstOrFail();
    }
}
