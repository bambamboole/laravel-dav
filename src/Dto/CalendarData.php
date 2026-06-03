<?php

namespace Bambamboole\LaravelDav\Dto;

final readonly class CalendarData
{
    /**
     * @param  array<int, string>  $components
     */
    public function __construct(
        public string $uri,
        public ?string $displayName = null,
        public ?string $description = null,
        public ?string $color = null,
        public ?string $timezone = null,
        public array $components = [],
        public int $syncToken = 1,
    ) {}
}
