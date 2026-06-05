<?php

namespace Bambamboole\LaravelDav\Support;

use Bambamboole\LaravelDav\Dto\CalendarData;
use Bambamboole\LaravelDav\Models\DavCalendar;

class CalendarHandle
{
    public function __construct(
        private CalendarObjectWriter $objects,
        public DavCalendar $model,
    ) {}

    public function objects(): CalendarObjectScope
    {
        return new CalendarObjectScope($this->objects, $this->model->user_id, $this->model);
    }

    public function data(): CalendarData
    {
        return new CalendarData(
            uri: $this->model->uri,
            displayName: $this->model->display_name,
            description: $this->model->description,
            color: $this->model->color,
            timezone: $this->model->timezone,
            components: $this->model->components,
            syncToken: $this->model->sync_token,
        );
    }
}
