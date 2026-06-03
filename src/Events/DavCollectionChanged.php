<?php

namespace Bambamboole\LaravelDav\Events;

use Illuminate\Foundation\Events\Dispatchable;

class DavCollectionChanged
{
    use Dispatchable;

    public function __construct(
        public int|string $ownerId,
        public string $type,
        public int $collectionId,
        public ?string $resourceUri,
        public string $operation,
        public int $syncToken,
    ) {}
}
