<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CalendarScope
{
    public function __construct(
        private CalendarObjectWriter $objects,
        private int|string $ownerId,
    ) {}

    /**
     * @return Collection<int, CalendarHandle>
     */
    public function get(): Collection
    {
        return $this->query()
            ->get()
            ->map(fn (DavCalendar $calendar): CalendarHandle => new CalendarHandle($this->objects, $calendar));
    }

    public function find(int|string $id): ?CalendarHandle
    {
        $calendar = $this->resourceQuery($id)->first();

        return $calendar instanceof DavCalendar ? new CalendarHandle($this->objects, $calendar) : null;
    }

    public function findOrFail(int|string $id): CalendarHandle
    {
        return new CalendarHandle($this->objects, $this->resourceQuery($id)->firstOrFail());
    }

    /**
     * @return Builder<DavCalendar>
     */
    private function query(): Builder
    {
        /** @var class-string<DavCalendar> $model */
        $model = Dav::modelFor('calendar', DavCalendar::class);

        return $model::query()
            ->where('user_id', $this->ownerId)
            ->orderBy('id');
    }

    /**
     * @return Builder<DavCalendar>
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
}
