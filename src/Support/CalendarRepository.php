<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\CalendarData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalendarRepository
{
    public function __construct(
        private CalendarObjectWriter $objects,
        private int|string $ownerId,
    ) {}

    /**
     * @return Collection<int, CalendarData>
     */
    public function get(): Collection
    {
        return $this->query()
            ->get()
            ->map(fn (DavCalendar $calendar): CalendarData => $this->data($calendar));
    }

    public function find(int|string $id): ?CalendarData
    {
        $calendar = $this->resourceQuery($id)->first();

        return $calendar instanceof DavCalendar ? $this->data($calendar) : null;
    }

    public function findOrFail(int|string $id): CalendarData
    {
        return $this->data($this->resourceQuery($id)->firstOrFail());
    }

    public function create(CalendarData $data): CalendarData
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

        return $this->data($calendar->refresh());
    }

    public function update(DavCalendar|int|string $calendar, CalendarData $data): CalendarData
    {
        return DB::transaction(function () use ($calendar, $data): CalendarData {
            $model = $this->model($calendar);

            $model->forceFill([
                'uri' => $data->uri,
                'display_name' => $data->displayName ?? $data->uri,
                'description' => $data->description,
                'color' => $data->color,
                'timezone' => $data->timezone,
                'components' => $data->components ?: ['VEVENT', 'VTODO', 'VJOURNAL'],
            ])->save();

            return $this->data($model->refresh());
        });
    }

    public function delete(DavCalendar|int|string $calendar): void
    {
        $this->model($calendar)->delete();
    }

    public function objects(DavCalendar|int|string $calendar): CalendarObjectRepository
    {
        $model = $this->model($calendar);

        return new CalendarObjectRepository($this->objects, $this->ownerId, $model);
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

    private function model(DavCalendar|int|string $calendar): DavCalendar
    {
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

    private function data(DavCalendar $calendar): CalendarData
    {
        return new CalendarData(
            uri: $calendar->uri,
            displayName: $calendar->display_name,
            description: $calendar->description,
            color: $calendar->color,
            timezone: $calendar->timezone,
            components: $calendar->components,
            syncToken: $calendar->sync_token,
        );
    }
}
