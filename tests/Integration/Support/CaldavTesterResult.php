<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

use JsonException;

final readonly class CaldavTesterResult
{
    /**
     * @param  array<string, SupportLevel>  $features  keyed by feature name
     * @param  list<string>  $erroredChecks
     */
    public function __construct(
        public array $features,
        public array $erroredChecks,
    ) {}

    /**
     * Build the result from the tester's raw `--format json` output. Volatile
     * metadata (version, timestamp, url, name) is ignored; only the feature map
     * is read.
     *
     * @param  list<string>  $erroredChecks
     *
     * @throws JsonException
     */
    public static function fromTesterOutput(string $json, array $erroredChecks): self
    {
        /** @var array{features?: array<string, array<string, mixed>>} $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $features = [];
        foreach ($decoded['features'] ?? [] as $name => $attributes) {
            $features[$name] = SupportLevel::fromTester((string) ($attributes['support'] ?? 'unknown'));
        }
        ksort($features);

        sort($erroredChecks);

        return new self($features, array_values($erroredChecks));
    }

    public function support(string $name): ?SupportLevel
    {
        return $this->features[$name] ?? null;
    }

    /**
     * @return list<string>
     */
    public function featureNames(): array
    {
        return array_keys($this->features);
    }
}
