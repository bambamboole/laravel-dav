<?php

namespace Bambamboole\LaravelDav\Casts\Concerns;

use JsonException;

trait DecodesJsonColumn
{
    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}
