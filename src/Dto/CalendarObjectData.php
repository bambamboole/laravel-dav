<?php

namespace Bambamboole\LaravelDav\Dto;

use Bambamboole\LaravelDav\Support\DtoFactory;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class CalendarObjectData implements Arrayable, JsonSerializable
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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return DtoFactory::calendarObjectData($data);
    }

    public function withStorageMeta(string $uri, string $etag, int $size): self
    {
        return DtoFactory::calendarObjectData($this, [
            'uri' => $uri,
            'etag' => $etag,
            'size' => $size,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return DtoFactory::calendarObjectDataArray($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
