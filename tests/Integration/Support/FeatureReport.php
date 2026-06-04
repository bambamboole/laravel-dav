<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

/**
 * A single feature outcome reported by caldav-server-tester.
 */
final readonly class FeatureReport
{
    public function __construct(
        public string $name,
        public SupportLevel $support,
        public ?string $note = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromTester(string $name, array $attributes): self
    {
        return new self(
            name: $name,
            support: SupportLevel::fromTester((string) ($attributes['support'] ?? 'unknown')),
            note: $attributes['behaviour'] ?? $attributes['description'] ?? $attributes['details'] ?? null,
        );
    }
}
