<?php

namespace Bambamboole\LaravelDav;

use Bambamboole\LaravelDav\Support\CalendarObjectWriter;
use Bambamboole\LaravelDav\Support\ContactCardWriter;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class LaravelDav
{
    /** @var array<string, class-string<Model>> */
    private const DEFAULTS = [
        'calendar' => Models\DavCalendar::class,
        'calendar_object' => Models\DavCalendarObject::class,
        'address_book' => Models\DavAddressBook::class,
        'card' => Models\DavCard::class,
        'credential' => Models\DavCredential::class,
    ];

    public function __construct(
        private ContactCardWriter $contacts,
        private CalendarObjectWriter $calendarObjects,
    ) {}

    public function contacts(): ContactCardWriter
    {
        return $this->contacts;
    }

    public function calendarObjects(): CalendarObjectWriter
    {
        return $this->calendarObjects;
    }

    /**
     * @return class-string<Model>
     */
    public function model(string $key): string
    {
        /** @var class-string<Model>|null $configured */
        $configured = config("dav.models.$key");

        return $configured
            ?? self::DEFAULTS[$key]
            ?? throw new InvalidArgumentException("Unknown dav model [$key].");
    }

    /**
     * Resolve a content model that must be (a subclass of) the given default.
     *
     * Relations use this so the related type stays the concrete package model
     * that consumers subclass; the runtime guard also enforces that an override
     * is actually a subclass of the package model it replaces.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $default
     * @return class-string<TModel>
     */
    public function modelFor(string $key, string $default): string
    {
        $model = $this->model($key);

        if (! is_a($model, $default, true)) {
            throw new InvalidArgumentException(
                "Configured dav model [{$model}] for [{$key}] must extend [{$default}]."
            );
        }

        return $model;
    }
}
