<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

use JsonException;

/**
 * The parsed outcome of a caldav-server-tester run: a typed feature/support map
 * plus the checks that aborted before they could be graded.
 */
final readonly class CaldavTesterResult
{
    /**
     * @param  array<string, FeatureReport>  $features  keyed by feature name
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
            $features[$name] = FeatureReport::fromTester($name, $attributes);
        }
        ksort($features);

        sort($erroredChecks);

        return new self($features, array_values($erroredChecks));
    }

    public function feature(string $name): ?FeatureReport
    {
        return $this->features[$name] ?? null;
    }

    public function support(string $name): ?SupportLevel
    {
        return $this->feature($name)?->support;
    }

    /**
     * @return list<string>
     */
    public function featureNames(): array
    {
        return array_keys($this->features);
    }
}
