<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\CalendarData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    public function create(CalendarData $data): CalendarHandle
    {
        $calendar = $this->modelClass()::query()->create([
            'user_id' => $this->ownerId,
            'uri' => $data->uri,
            'display_name' => $data->displayName ?? $data->uri,
            'description' => $data->description,
            'color' => $data->color,
            'timezone' => $data->timezone,
            'components' => $data->components ?: ['VEVENT', 'VTODO', 'VJOURNAL'],
            'sync_token' => $data->syncToken,
        ]);

        return new CalendarHandle($this->objects, $calendar->refresh());
    }

    public function update(DavCalendar|CalendarHandle|int|string $calendar, CalendarData $data): CalendarHandle
    {
        return DB::transaction(function () use ($calendar, $data): CalendarHandle {
            $model = $this->model($calendar);

            $model->forceFill([
                'uri' => $data->uri,
                'display_name' => $data->displayName ?? $data->uri,
                'description' => $data->description,
                'color' => $data->color,
                'timezone' => $data->timezone,
                'components' => $data->components ?: ['VEVENT', 'VTODO', 'VJOURNAL'],
            ])->save();

            return new CalendarHandle($this->objects, $model->refresh());
        });
    }

    public function delete(DavCalendar|CalendarHandle|int|string $calendar): void
    {
        $this->model($calendar)->delete();
    }

    /**
     * @return Builder<DavCalendar>
     */
    private function query(): Builder
    {
        return $this->modelClass()::query()
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

    private function model(DavCalendar|CalendarHandle|int|string $calendar): DavCalendar
    {
        if ($calendar instanceof CalendarHandle) {
            $calendar = $calendar->model;
        }

        return $calendar instanceof DavCalendar
            ? $this->resourceQuery((string) $calendar->getKey())->firstOrFail()
            : $this->resourceQuery($calendar)->firstOrFail();
    }

    /**
     * @return class-string<DavCalendar>
     */
    private function modelClass(): string
    {
        return Dav::modelFor('calendar', DavCalendar::class);
    }
}
