<?php

namespace Bambamboole\LaravelDav\Support;

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
}
