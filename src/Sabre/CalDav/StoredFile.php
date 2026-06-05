<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

final readonly class StoredFile
{
    public function __construct(
        public string $managedId,
        public string $filename,
        public string $contentType,
        public int $size,
        public string $etag,
        public string $storageDisk,
        public string $storagePath,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function attributes(): array
    {
        return [
            'managed_id' => $this->managedId,
            'filename' => $this->filename,
            'content_type' => $this->contentType,
            'size' => $this->size,
            'etag' => $this->etag,
            'storage_disk' => $this->storageDisk,
            'storage_path' => $this->storagePath,
        ];
    }
}
