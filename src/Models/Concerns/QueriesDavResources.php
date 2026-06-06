<?php

namespace Bambamboole\LaravelDav\Models\Concerns;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait QueriesDavResources
{
    use ResolvesDavOwner;

    /**
     * Scope to a single resource by primary key or by its DAV uri.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forKey(Builder $query, int|string $id): Builder
    {
        return $query->where(function (Builder $query) use ($id): void {
            if (is_numeric($id)) {
                $query->whereKey($id);
            }

            $query->orWhere('uri', $id);
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    #[Scope]
    protected function forOwner(Builder $query, DavOwner|int|string $owner): Builder
    {
        return $query->where('owner_id', self::resolveOwnerId($owner));
    }
}
