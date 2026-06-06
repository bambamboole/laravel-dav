<?php

namespace Bambamboole\LaravelDav\Models\Concerns;

use Bambamboole\LaravelDav\Facades\Dav;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToDavOwner
{
    /**
     * @return BelongsTo<Model, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Dav::ownerModel(), 'owner_id');
    }
}
