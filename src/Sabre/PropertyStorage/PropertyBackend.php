<?php

namespace Bambamboole\LaravelDav\Sabre\PropertyStorage;

use Bambamboole\LaravelDav\Models\DavProperty;
use Sabre\DAV\PropertyStorage\Backend\BackendInterface;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Xml\Property\Complex;

class PropertyBackend implements BackendInterface
{
    public function propFind($path, PropFind $propFind): void
    {
        $properties = DavProperty::query()->where('path', $path)->get(['name', 'value_type', 'value']);

        foreach ($properties as $property) {
            $propFind->set($property->name, $this->restoreValue($property->value_type, $property->value));
        }
    }

    public function propPatch($path, PropPatch $propPatch): void
    {
        $propPatch->handleRemaining(function (array $properties) use ($path): bool {
            foreach ($properties as $name => $value) {
                if ($value === null) {
                    DavProperty::query()->where('path', $path)->where('name', $name)->delete();

                    continue;
                }

                DavProperty::query()->updateOrCreate(
                    ['path' => $path, 'name' => $name],
                    $this->storedValue($value),
                );
            }

            return true;
        });
    }

    public function delete($path): void
    {
        DavProperty::query()
            ->where(fn ($query) => $query
                ->where('path', $path)
                ->orWhere('path', 'like', $this->childPathPattern($path)))
            ->delete();
    }

    public function move($source, $destination): void
    {
        DavProperty::query()
            ->where(fn ($query) => $query
                ->where('path', $source)
                ->orWhere('path', 'like', $this->childPathPattern($source)))
            ->get(['id', 'path'])
            ->each(function (DavProperty $property) use ($source, $destination): void {
                if ($property->path === $source) {
                    $property->update(['path' => $destination]);

                    return;
                }

                $property->update([
                    'path' => $destination.'/'.substr($property->path, strlen($source) + 1),
                ]);
            });
    }

    /** @return array{value_type: string, value: string|null} */
    private function storedValue(mixed $value): array
    {
        if (is_scalar($value)) {
            return [
                'value_type' => 'string',
                'value' => (string) $value,
            ];
        }

        if ($value instanceof Complex) {
            return [
                'value_type' => 'xml',
                'value' => $value->getXml(),
            ];
        }

        return [
            'value_type' => 'object',
            'value' => serialize($value),
        ];
    }

    private function restoreValue(?string $valueType, ?string $value): mixed
    {
        return match ($valueType) {
            null, 'string', '1' => $value,
            'xml', '2' => new Complex((string) $value),
            'object', '3' => unserialize((string) $value),
            default => $value,
        };
    }

    private function childPathPattern(string $path): string
    {
        return rtrim($path, '/').'/%';
    }
}
