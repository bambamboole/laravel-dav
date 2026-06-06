<?php

namespace Bambamboole\LaravelDav;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class LaravelDav
{
    /** @var array<class-string<Model>, string> */
    private const DEFAULTS = [
        Models\DavCalendar::class => 'calendar',
        Models\DavCalendarAttachment::class => 'calendar_attachment',
        Models\DavCalendarInstance::class => 'calendar_instance',
        Models\DavCalendarObject::class => 'calendar_object',
        Models\DavCalendarProxyMembership::class => 'calendar_proxy_membership',
        Models\DavCalendarSubscription::class => 'calendar_subscription',
        Models\DavAddressBook::class => 'address_book',
        Models\DavCard::class => 'card',
        Models\DavCredential::class => 'credential',
        Models\DavSchedulingObject::class => 'scheduling_object',
    ];

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $default
     * @return class-string<TModel>
     */
    public function model(string $default): string
    {
        $key = self::DEFAULTS[$default] ?? null;

        if ($key === null) {
            throw new InvalidArgumentException("Unknown dav model default [{$default}].");
        }

        $model = config("dav.models.$key");

        if ($model === null) {
            return $default;
        }

        if (! is_string($model)) {
            throw new InvalidArgumentException("Configured dav model for [{$key}] must be an Eloquent model class.");
        }

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

    public function baseUri(): string
    {
        $prefix = trim((string) config('dav.route.prefix', 'dav'), '/');

        return $prefix === '' ? '/' : "/{$prefix}/";
    }
}
