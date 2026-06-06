<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavCalendarInstanceFactory;
use Bambamboole\LaravelDav\Dto\CalendarObjectData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\Concerns\BelongsToDavOwner;
use Bambamboole\LaravelDav\Models\Concerns\QueriesDavResources;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Sabre\DAV\Sharing\Plugin as SharingPlugin;

/**
 * @property int $id
 * @property int $dav_calendar_id
 * @property int $owner_id
 * @property string $uri
 * @property int $access
 * @property string $display_name
 * @property string|null $description
 * @property string|null $color
 * @property string|null $timezone
 * @property int $order
 * @property bool $transparent
 * @property string|null $share_href
 * @property string|null $share_display_name
 * @property int|null $share_invite_status
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read DavCalendar $calendar
 */
class DavCalendarInstance extends Model
{
    use BelongsToDavOwner;

    /** @use HasFactory<DavCalendarInstanceFactory> */
    use HasFactory;

    use QueriesDavResources;

    public const AccessOwner = SharingPlugin::ACCESS_SHAREDOWNER;

    public const AccessRead = SharingPlugin::ACCESS_READ;

    public const AccessReadWrite = SharingPlugin::ACCESS_READWRITE;

    protected $table = 'dav_calendar_instances';

    protected $fillable = [
        'dav_calendar_id',
        'owner_id',
        'uri',
        'access',
        'display_name',
        'description',
        'color',
        'timezone',
        'order',
        'transparent',
        'share_href',
        'share_display_name',
        'share_invite_status',
    ];

    protected $attributes = [
        'access' => self::AccessOwner,
        'order' => 0,
        'transparent' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access' => 'integer',
            'order' => 'integer',
            'transparent' => 'boolean',
            'share_invite_status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<DavCalendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Dav::model(DavCalendar::class), 'dav_calendar_id');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateDavProperties(array $attributes): static
    {
        DB::transaction(function () use ($attributes): void {
            if (array_key_exists('components', $attributes)) {
                $this->calendar->forceFill(['components' => $attributes['components']])->save();
            }

            $values = array_intersect_key($attributes, array_flip([
                'uri',
                'display_name',
                'description',
                'color',
                'timezone',
                'order',
                'transparent',
                'share_display_name',
            ]));

            if ($values !== []) {
                $this->forceFill($values)->save();
            }
        });

        return $this;
    }

    public function putObject(CalendarObjectData|string $data, ?string $uri = null, ?string $expectedEtag = null): DavCalendarObject
    {
        return $this->calendar->putObject($data, $uri, $expectedEtag);
    }

    public function deleteDavCollection(): void
    {
        DB::transaction(function (): void {
            if ($this->access !== self::AccessOwner) {
                $this->delete();

                return;
            }

            $this->calendar->instances()->delete();
            $this->calendar->delete();
        });
    }

    protected static function newFactory(): DavCalendarInstanceFactory
    {
        return DavCalendarInstanceFactory::new();
    }
}
