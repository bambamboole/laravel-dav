<?php

namespace Bambamboole\LaravelDav\Casts;

use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Support\DtoFactory;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/**
 * @implements CastsAttributes<ContactData, ContactData|array<string, mixed>|null>
 */
class ContactDataCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ContactData
    {
        return DtoFactory::contactData($this->decode($value), [
            'uri' => (string) ($attributes['uri'] ?? ''),
            'raw' => (string) ($attributes['card_data'] ?? ''),
            'etag' => (string) ($attributes['etag'] ?? ''),
            'size' => (int) ($attributes['size'] ?? 0),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{data: string}
     *
     * @throws JsonException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $data = $value instanceof ContactData ? $value : DtoFactory::contactData(is_array($value) ? $value : []);

        return [
            'data' => json_encode(DtoFactory::contactStorageData($data), JSON_THROW_ON_ERROR),
        ];
    }

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
