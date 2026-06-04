<?php

namespace Bambamboole\LaravelDav\Sabre\PropertyStorage;

use Bambamboole\LaravelDav\Models\DavProperty;
use Sabre\DAV\PropertyStorage\Backend\BackendInterface;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;

class PropertyBackend implements BackendInterface
{
    public function propFind($path, PropFind $propFind): void
    {
        $properties = DavProperty::query()->where('path', $path)->get(['name', 'value']);

        foreach ($properties as $property) {
            $propFind->set($property->name, $property->value);
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
                    ['value' => (string) $value],
                );
            }

            return true;
        });
    }

    public function delete($path): void
    {
        DavProperty::query()
            ->where('path', $path)
            ->orWhere('path', 'like', rtrim($path, '/').'/%')
            ->delete();
    }

    public function move($source, $destination): void
    {
        DavProperty::query()->where('path', $source)->update(['path' => $destination]);
    }
}
