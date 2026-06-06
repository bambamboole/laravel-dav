<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavCalendarAttachmentFactory;
use Bambamboole\LaravelDav\Facades\Dav;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $dav_calendar_object_id
 * @property int $created_by_owner_id
 * @property string $managed_id
 * @property string|null $filename
 * @property string|null $content_type
 * @property int $size
 * @property string $etag
 * @property string $storage_disk
 * @property string $storage_path
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DavCalendarObject $calendarObject
 */
class DavCalendarAttachment extends Model
{
    /** @use HasFactory<DavCalendarAttachmentFactory> */
    use HasFactory;

    protected $table = 'dav_calendar_attachments';

    protected $fillable = [
        'dav_calendar_object_id',
        'created_by_owner_id',
        'managed_id',
        'filename',
        'content_type',
        'size',
        'etag',
        'storage_disk',
        'storage_path',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DavCalendarObject, $this>
     */
    public function calendarObject(): BelongsTo
    {
        return $this->belongsTo(Dav::modelFor('calendar_object', DavCalendarObject::class), 'dav_calendar_object_id');
    }

    protected static function newFactory(): DavCalendarAttachmentFactory
    {
        return DavCalendarAttachmentFactory::new();
    }
}
