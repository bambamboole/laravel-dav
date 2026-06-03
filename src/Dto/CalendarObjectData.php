<?php

namespace Bambamboole\LaravelDav\Dto;

use Carbon\CarbonImmutable;

final readonly class CalendarObjectData
{
    public function __construct(
        public string $uri,
        public string $raw,
        public string $etag,
        public int $size,
        public ?string $uid = null,
        public ?string $componentType = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $location = null,
        public ?string $status = null,
        public ?string $url = null,
        public ?CarbonImmutable $startsAt = null,
        public ?CarbonImmutable $endsAt = null,
        public bool $isAllDay = false,
        public ?string $timezone = null,
    ) {}

    public function withStorageMeta(string $uri, string $etag, int $size): self
    {
        return new self(
            uri: $uri,
            raw: $this->raw,
            etag: $etag,
            size: $size,
            uid: $this->uid,
            componentType: $this->componentType,
            summary: $this->summary,
            description: $this->description,
            location: $this->location,
            status: $this->status,
            url: $this->url,
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            isAllDay: $this->isAllDay,
            timezone: $this->timezone,
        );
    }
}
