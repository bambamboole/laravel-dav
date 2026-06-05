<?php

namespace Bambamboole\LaravelDav\Models\Concerns;

use Bambamboole\LaravelDav\Exceptions\StaleDavResourceException;
use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Support\DavChangeOperation;
use Bambamboole\LaravelDav\Support\DavChangeRecorder;
use Carbon\CarbonImmutable;

/**
 * Shared write behaviour for versioned DAV resources (calendar objects and
 * cards). The model is the single source of truth: every save derives the
 * payload, etag, and size, enforces optimistic concurrency, and records a
 * sync change — regardless of whether the write came from the DAV protocol or
 * the typed Eloquent API.
 */
trait TracksDavResource
{
    private ?string $expectedEtag = null;

    /**
     * Require the stored resource to still carry this etag when it is saved,
     * throwing a StaleDavResourceException otherwise (optimistic concurrency).
     */
    public function expectingEtag(?string $etag): static
    {
        $this->expectedEtag = $etag;

        return $this;
    }

    abstract protected function payloadColumn(): string;

    abstract protected function changeCollection(): DavCalendar|DavAddressBook;

    abstract protected function buildPayload(): string;

    /**
     * Fill in resource defaults (uid, uri) before the payload is derived.
     */
    abstract protected function applyDavDefaults(): void;

    public static function bootTracksDavResource(): void
    {
        static::saving(function (self $resource): void {
            $resource->applyDavDefaults();
            $resource->guardExpectedEtag();
            $resource->refreshPayload();
        });

        static::saved(function (self $resource): void {
            $resource->recordChange($resource->wasRecentlyCreated
                ? DavChangeOperation::Add
                : DavChangeOperation::Modify);
        });

        static::deleting(function (self $resource): void {
            $resource->guardExpectedEtag();
        });

        static::deleted(function (self $resource): void {
            $resource->recordChange(DavChangeOperation::Delete);
        });
    }

    private function guardExpectedEtag(): void
    {
        if ($this->exists && $this->expectedEtag !== null && $this->getOriginal('etag') !== $this->expectedEtag) {
            throw new StaleDavResourceException($this->expectedEtag, (string) $this->getOriginal('etag'), $this->uri);
        }
    }

    private function refreshPayload(): void
    {
        $column = $this->payloadColumn();

        if (! $this->isDirty($column) || blank($this->{$column})) {
            $this->{$column} = $this->buildPayload();
        }

        $payload = (string) $this->{$column};

        $this->etag = sha1($payload);
        $this->size = strlen($payload);
        $this->last_modified_at = CarbonImmutable::now();
    }

    private function recordChange(DavChangeOperation $operation): void
    {
        app(DavChangeRecorder::class)->record($this->changeCollection(), $this->uri, $operation);
    }
}
