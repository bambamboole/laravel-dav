<?php

namespace Bambamboole\LaravelDav\Dto;

use Carbon\CarbonImmutable;
use DateTimeInterface;

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

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uri: self::string($data, 'uri'),
            raw: self::string($data, 'raw'),
            etag: self::string($data, 'etag'),
            size: self::int($data, 'size'),
            uid: self::nullableString($data, 'uid'),
            componentType: self::nullableString($data, 'component_type') ?? self::nullableString($data, 'componentType'),
            summary: self::nullableString($data, 'summary'),
            description: self::nullableString($data, 'description'),
            location: self::nullableString($data, 'location'),
            status: self::nullableString($data, 'status'),
            url: self::nullableString($data, 'url'),
            startsAt: self::dateTime($data['starts_at'] ?? $data['startsAt'] ?? null, self::nullableString($data, 'timezone')),
            endsAt: self::dateTime($data['ends_at'] ?? $data['endsAt'] ?? null, self::nullableString($data, 'timezone')),
            isAllDay: self::bool($data['is_all_day'] ?? $data['isAllDay'] ?? $data['all_day'] ?? false),
            timezone: self::nullableString($data, 'timezone'),
        );
    }

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

    /**
     * @param  array<string, mixed>  $data
     */
    private static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key, string $default = ''): string
    {
        return self::nullableString($data, $key) ?? $default;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    private static function bool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function dateTime(mixed $value, ?string $timezone): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        return CarbonImmutable::parse((string) $value, $timezone);
    }
}
