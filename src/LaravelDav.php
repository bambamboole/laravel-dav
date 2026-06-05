<?php

namespace Bambamboole\LaravelDav;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class LaravelDav
{
    /** @var array<string, class-string<Model>> */
    private const DEFAULTS = [
        'calendar' => Models\DavCalendar::class,
        'calendar_attachment' => Models\DavCalendarAttachment::class,
        'calendar_instance' => Models\DavCalendarInstance::class,
        'calendar_object' => Models\DavCalendarObject::class,
        'calendar_proxy_membership' => Models\DavCalendarProxyMembership::class,
        'calendar_subscription' => Models\DavCalendarSubscription::class,
        'address_book' => Models\DavAddressBook::class,
        'card' => Models\DavCard::class,
        'credential' => Models\DavCredential::class,
        'scheduling_object' => Models\DavSchedulingObject::class,
    ];

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

    /**
     * @return class-string<Model>
     */
    public function ownerModel(): string
    {
        $model = config('dav.models.owner');

        if (! is_string($model) || ! is_a($model, Model::class, true)) {
            throw new InvalidArgumentException('The dav.models.owner config value must be an Eloquent model class.');
        }

        return $model;
    }

    public function ownerTable(): string
    {
        $model = $this->ownerModel();

        return (new $model)->getTable();
    }
}
