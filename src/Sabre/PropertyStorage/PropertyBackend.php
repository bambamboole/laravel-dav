<?php

namespace Bambamboole\LaravelDav\Sabre\PropertyStorage;

use Illuminate\Support\Facades\DB;
use Sabre\DAV\PropertyStorage\Backend\BackendInterface;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;

class PropertyBackend implements BackendInterface
{
    public function propFind($path, PropFind $propFind): void
    {
        $rows = DB::table('dav_properties')->where('path', $path)->get(['name', 'value']);

        foreach ($rows as $row) {
            $propFind->set($row->name, $row->value);
        }
    }

    public function propPatch($path, PropPatch $propPatch): void
    {
        $propPatch->handleRemaining(function (array $properties) use ($path): bool {
            foreach ($properties as $name => $value) {
                if ($value === null) {
                    DB::table('dav_properties')->where('path', $path)->where('name', $name)->delete();

                    continue;
                }

                DB::table('dav_properties')->updateOrInsert(
                    ['path' => $path, 'name' => $name],
                    ['value' => is_string($value) ? $value : (string) $value],
                );
            }

            return true;
        });
    }

    public function delete($path): void
    {
        DB::table('dav_properties')
            ->where('path', $path)
            ->orWhere('path', 'like', rtrim($path, '/').'/%')
            ->delete();
    }

    public function move($source, $destination): void
    {
        DB::table('dav_properties')->where('path', $source)->update(['path' => $destination]);
    }
}
