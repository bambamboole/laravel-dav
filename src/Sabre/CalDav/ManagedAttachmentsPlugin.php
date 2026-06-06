<?php

namespace Bambamboole\LaravelDav\Sabre\CalDav;

use Bambamboole\LaravelDav\Contracts\DavOwner;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendarAttachment;
use Bambamboole\LaravelDav\Models\DavCalendarInstance;
use Bambamboole\LaravelDav\Models\DavCalendarObject;
use Bambamboole\LaravelDav\Models\DavCalendarProxyMembership;
use Bambamboole\LaravelDav\Sabre\Concerns\ResolvesPrincipalUri;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Sabre\DAV\Exception\BadRequest;
use Sabre\DAV\Exception\Forbidden;
use Sabre\DAV\Exception\NotFound;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\DAVACL\Plugin as AclPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\VObject\Component;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Parameter;
use Sabre\VObject\Property;
use Sabre\VObject\Reader;
use Throwable;

class ManagedAttachmentsPlugin extends ServerPlugin
{
    use ResolvesPrincipalUri;

    private Server $server;

    public function __construct(private readonly CalendarBackend $calendarBackend) {}

    public function initialize(Server $server): void
    {
        $this->server = $server;

        $server->on('beforeMethod:PUT', [$this, 'beforeWriteMethod'], 30);
        $server->on('beforeMethod:DELETE', [$this, 'beforeWriteMethod'], 30);
        $server->on('method:GET', [$this, 'httpGet'], 10);
        $server->on('method:POST', [$this, 'httpPost'], 10);
    }

    /**
     * @return array<int, string>
     */
    public function getFeatures(): array
    {
        return [
            'calendar-managed-attachments',
            'calendar-managed-attachments-no-recurrence',
        ];
    }

    public function beforeWriteMethod(RequestInterface $request, ResponseInterface $response): bool
    {
        if ($this->attachmentManagedIdFromPath($request->getPath()) !== null) {
            $response->setStatus(405);
            $response->setHeader('Allow', 'GET');
            $response->setHeader('Content-Length', '0');

            return false;
        }

        if ($request->getMethod() === 'PUT') {
            $this->ensureManagedIdsCanBeReused($request);
        }

        return true;
    }

    public function httpGet(RequestInterface $request, ResponseInterface $response): bool
    {
        $managedId = $this->attachmentManagedIdFromPath($request->getPath());

        if ($managedId === null) {
            return true;
        }

        $attachment = $this->attachment($managedId);

        if (! $this->currentPrincipalCanRead($attachment)) {
            throw new Forbidden('You do not have access to this managed attachment.');
        }

        $disk = Storage::disk($attachment->storage_disk);

        if (! $disk->exists($attachment->storage_path)) {
            throw new NotFound('Managed attachment file not found.');
        }

        $body = $disk->get($attachment->storage_path);
        $filename = $attachment->filename ?: 'attachment';

        $response->setStatus(200);
        $response->setHeader('Content-Type', $attachment->content_type ?: 'application/octet-stream');
        $response->setHeader('Content-Length', (string) strlen((string) $body));
        $response->setHeader('Content-Disposition', 'attachment; filename="'.$this->quoteHeaderValue($filename).'"');
        $response->setHeader('ETag', '"'.$attachment->etag.'"');
        $response->setBody($body);

        return false;
    }

    public function httpPost(RequestInterface $request, ResponseInterface $response): bool
    {
        $query = $request->getQueryParameters();
        $action = $query['action'] ?? null;

        if (! in_array($action, ['attachment-add', 'attachment-update', 'attachment-remove'], true)) {
            return true;
        }

        $target = $this->targetCalendarObject($request->getPath());
        $this->acl()->checkPrivileges($request->getPath(), '{DAV:}write-content');
        $this->ensureRecurrenceIsNotTargeted($query);
        $this->ensureCurrentUserCanMutateScheduledAttachments($target);

        match ($action) {
            'attachment-add' => $this->addAttachment($request, $response, $target, $query),
            'attachment-update' => $this->updateAttachment($request, $response, $target, $query),
            'attachment-remove' => $this->removeAttachment($response, $target, $query),
        };

        return false;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function addAttachment(RequestInterface $request, ResponseInterface $response, DavCalendarObject $object, array $query): void
    {
        if (isset($query['managed-id'])) {
            throw new BadRequest('The managed-id query parameter is not valid for attachment-add.');
        }

        $stored = $this->storeRequestBody($request, $object);

        try {
            DB::transaction(function () use ($object, $stored): void {
                $payload = $this->calendarWithAddedAttachment($object->calendar_data, $stored);

                $this->calendarBackend->updateCalendarObject($object->dav_calendar_id, $object->uri, $payload);

                $this->createAttachmentRow($object, $stored);
            });
        } catch (Throwable $throwable) {
            Storage::disk($stored->storageDisk)->delete($stored->storagePath);

            throw $throwable;
        }

        $response->setStatus(200);
        $response->setHeader('Cal-Managed-ID', $stored->managedId);
        $response->setHeader('Content-Length', '0');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function updateAttachment(RequestInterface $request, ResponseInterface $response, DavCalendarObject $object, array $query): void
    {
        $oldManagedId = $this->managedIdFromQuery($query);
        $oldAttachment = $this->attachmentForObject($object, $oldManagedId);
        $stored = $this->storeRequestBody($request, $object);

        try {
            DB::transaction(function () use ($object, $oldAttachment, $oldManagedId, $stored): void {
                $payload = $this->calendarWithReplacedAttachment($object->calendar_data, $oldManagedId, $stored);

                $this->calendarBackend->updateCalendarObject($object->dav_calendar_id, $object->uri, $payload);
                $oldAttachment->delete();

                $this->createAttachmentRow($object, $stored);
            });
        } catch (Throwable $throwable) {
            Storage::disk($stored->storageDisk)->delete($stored->storagePath);

            throw $throwable;
        }

        Storage::disk($oldAttachment->storage_disk)->delete($oldAttachment->storage_path);

        $response->setStatus(200);
        $response->setHeader('Cal-Managed-ID', $stored->managedId);
        $response->setHeader('Content-Length', '0');
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function removeAttachment(ResponseInterface $response, DavCalendarObject $object, array $query): void
    {
        $managedId = $this->managedIdFromQuery($query);
        $attachment = $this->attachmentForObject($object, $managedId);

        DB::transaction(function () use ($object, $attachment, $managedId): void {
            $payload = $this->calendarWithoutAttachment($object->calendar_data, $managedId);

            $this->calendarBackend->updateCalendarObject($object->dav_calendar_id, $object->uri, $payload);
            $attachment->delete();
        });

        Storage::disk($attachment->storage_disk)->delete($attachment->storage_path);

        $response->setStatus(204);
        $response->setHeader('Content-Length', '0');
    }

    private function storeRequestBody(RequestInterface $request, DavCalendarObject $object): StoredFile
    {
        $body = $request->getBodyAsString();
        $managedId = (string) Str::uuid();
        $filename = $this->filename($request);
        $contentType = $request->getHeader('Content-Type') ?: 'application/octet-stream';
        $disk = (string) config('dav.attachments.disk', config('filesystems.default'));
        $basePath = trim((string) config('dav.attachments.path', 'dav-attachments'), '/');
        $storagePath = ($basePath === '' ? '' : $basePath.'/').$object->dav_calendar_id.'/'.$object->getKey().'/'.$managedId;

        Storage::disk($disk)->put($storagePath, $body);

        return new StoredFile(
            managedId: $managedId,
            filename: $filename,
            contentType: $contentType,
            size: strlen($body),
            etag: sha1($body),
            storageDisk: $disk,
            storagePath: $storagePath,
        );
    }

    private function calendarWithAddedAttachment(string $payload, StoredFile $attachment): string
    {
        return $this->rewriteCalendar($payload, function (Component $component) use ($attachment): void {
            $this->addAttachmentProperty($component, $attachment);
        });
    }

    private function calendarWithReplacedAttachment(string $payload, string $managedId, StoredFile $attachment): string
    {
        return $this->rewriteCalendar($payload, function (Component $component) use ($managedId, $attachment): void {
            $property = $this->findAttachment($component, $managedId);

            if ($property !== null) {
                $component->remove($property);
                $this->addAttachmentProperty($component, $attachment);
            }
        }, requireManagedId: $managedId);
    }

    private function calendarWithoutAttachment(string $payload, string $managedId): string
    {
        return $this->rewriteCalendar($payload, function (Component $component) use ($managedId): void {
            $property = $this->findAttachment($component, $managedId);

            if ($property !== null) {
                $component->remove($property);
            }
        }, requireManagedId: $managedId);
    }

    /**
     * @param  callable(Component): void  $callback
     */
    private function rewriteCalendar(string $payload, callable $callback, ?string $requireManagedId = null): string
    {
        $calendar = $this->readCalendar($payload);
        $matched = false;

        try {
            foreach ($this->attachmentComponents($calendar) as $component) {
                if ($requireManagedId !== null && ! $this->componentHasManagedId($component, $requireManagedId)) {
                    continue;
                }

                $callback($component);
                $matched = true;
            }

            if ($requireManagedId !== null && ! $matched) {
                throw new BadRequest('The managed-id query parameter does not match an ATTACH property.');
            }

            return $calendar->serialize();
        } finally {
            $calendar->destroy();
        }
    }

    private function addAttachmentProperty(Component $component, StoredFile $attachment): void
    {
        $component->add('ATTACH', $this->attachmentUrl($attachment->managedId, $attachment->filename), [
            'MANAGED-ID' => $attachment->managedId,
            'FMTTYPE' => $attachment->contentType,
            'FILENAME' => $attachment->filename,
            'SIZE' => (string) $attachment->size,
        ]);
    }

    private function targetCalendarObject(string $path): DavCalendarObject
    {
        $segments = explode('/', trim($path, '/'), 4);

        if (count($segments) !== 4 || $segments[0] !== config('dav.calendar_prefix', 'calendars') || ! ctype_digit($segments[1])) {
            throw new NotFound('Calendar object not found.');
        }

        $instance = Dav::model(DavCalendarInstance::class)::query()
            ->where('owner_id', (int) $segments[1])
            ->where('uri', $segments[2])
            ->first();

        if (! $instance instanceof DavCalendarInstance) {
            throw new NotFound('Calendar object not found.');
        }

        $object = Dav::model(DavCalendarObject::class)::query()
            ->where('dav_calendar_id', $instance->dav_calendar_id)
            ->where('uri', $segments[3])
            ->first();

        if (! $object instanceof DavCalendarObject) {
            throw new NotFound('Calendar object not found.');
        }

        return $object;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function managedIdFromQuery(array $query): string
    {
        $managedId = $query['managed-id'] ?? null;

        if (! is_string($managedId) || $managedId === '') {
            throw new BadRequest('The managed-id query parameter is required.');
        }

        return $managedId;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function ensureRecurrenceIsNotTargeted(array $query): void
    {
        if (isset($query['rid'])) {
            throw new BadRequest('Managed attachments for recurrence instances are not supported.');
        }
    }

    private function attachmentForObject(DavCalendarObject $object, string $managedId): DavCalendarAttachment
    {
        $attachment = $this->attachments()
            ->where('dav_calendar_object_id', $object->getKey())
            ->where('managed_id', $managedId)
            ->first();

        if (! $attachment instanceof DavCalendarAttachment) {
            throw new BadRequest('The managed-id query parameter does not match a managed attachment.');
        }

        return $attachment;
    }

    private function createAttachmentRow(DavCalendarObject $object, StoredFile $stored): void
    {
        $this->attachments()->create([
            'dav_calendar_object_id' => $object->getKey(),
            'created_by_owner_id' => $this->currentOwnerId(),
            ...$stored->attributes(),
        ]);
    }

    /**
     * @return Builder<DavCalendarAttachment>
     */
    private function attachments(): Builder
    {
        return Dav::model(DavCalendarAttachment::class)::query();
    }

    private function attachment(string $managedId): DavCalendarAttachment
    {
        $attachment = $this->attachments()
            ->where('managed_id', $managedId)
            ->with('calendarObject.calendar')
            ->first();

        if (! $attachment instanceof DavCalendarAttachment) {
            throw new NotFound('Managed attachment not found.');
        }

        return $attachment;
    }

    private function filename(RequestInterface $request): string
    {
        $contentDisposition = $request->getHeader('Content-Disposition') ?? '';
        $filename = null;

        if (preg_match('/(?:^|;)\\s*filename\\*?=(?:"([^"]*)"|([^;]*))/i', $contentDisposition, $matches) === 1) {
            $filename = stripcslashes($matches[1] !== '' ? $matches[1] : $matches[2]);
        }

        $filename = basename((string) $filename);
        $filename = trim(preg_replace('/[[:cntrl:]]/', '', $filename) ?: '');

        return $filename !== '' ? $filename : 'attachment';
    }

    private function attachmentUrl(string $managedId, string $filename): string
    {
        $baseUri = rtrim($this->server->getBaseUri(), '/');

        return url($baseUri.'/attachments/'.$managedId.'/'.rawurlencode($filename));
    }

    private function attachmentManagedIdFromPath(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'), 3);

        if (count($segments) < 2 || $segments[0] !== 'attachments' || $segments[1] === '') {
            return null;
        }

        return rawurldecode($segments[1]);
    }

    private function currentPrincipalCanRead(DavCalendarAttachment $attachment): bool
    {
        $ownerId = $this->currentOwnerId();

        if ($ownerId === null) {
            return false;
        }

        $object = $attachment->calendarObject;

        $calendar = $object->calendar;

        if ((int) $calendar->owner_id === $ownerId) {
            return true;
        }

        $hasSharedInstance = Dav::model(DavCalendarInstance::class)::query()
            ->where('dav_calendar_id', $object->dav_calendar_id)
            ->where('owner_id', $ownerId)
            ->whereIn('access', [DavCalendarInstance::AccessOwner, DavCalendarInstance::AccessRead, DavCalendarInstance::AccessReadWrite])
            ->exists();

        if ($hasSharedInstance) {
            return true;
        }

        return Dav::model(DavCalendarProxyMembership::class)::query()
            ->where('owner_id', $calendar->owner_id)
            ->where('delegate_owner_id', $ownerId)
            ->whereIn('access', [DavCalendarProxyMembership::AccessRead, DavCalendarProxyMembership::AccessWrite])
            ->exists();
    }

    private function ensureCurrentUserCanMutateScheduledAttachments(DavCalendarObject $object): void
    {
        $owner = $this->currentOwner();

        if (! $owner instanceof DavOwner || ($email = $owner->getDavPrincipalEmail()) === null) {
            return;
        }

        $calendar = $this->readCalendar($object->calendar_data);

        try {
            foreach ($this->attachmentComponents($calendar) as $component) {
                if (! isset($component->ORGANIZER) || ! isset($component->ATTENDEE)) {
                    continue;
                }

                if ($this->componentHasAddress($component, 'ORGANIZER', $email)) {
                    return;
                }

                if ($this->componentHasAddress($component, 'ATTENDEE', $email)) {
                    throw new Forbidden('Attendees cannot manage attachments on scheduled events.');
                }
            }
        } finally {
            $calendar->destroy();
        }
    }

    private function ensureManagedIdsCanBeReused(RequestInterface $request): void
    {
        if (! str_contains((string) $request->getHeader('Content-Type'), 'text/calendar')) {
            return;
        }

        $body = $request->getBodyAsString();

        if (stripos($body, 'MANAGED-ID') === false) {
            return;
        }

        try {
            $calendar = $this->readCalendar($body);
        } catch (BadRequest) {
            return;
        }

        try {
            $managedIds = [];

            foreach ($this->attachmentComponents($calendar) as $component) {
                foreach ($component->select('ATTACH') as $property) {
                    if ($property instanceof Property) {
                        $managedId = $this->managedIdParameter($property);

                        if ($managedId !== null) {
                            $managedIds[] = $managedId;
                        }
                    }
                }
            }
        } finally {
            $calendar->destroy();
        }

        if ($managedIds === []) {
            return;
        }

        $managedIds = array_unique($managedIds);
        $currentOwnerId = $this->currentOwnerId();

        $attachments = $this->attachments()
            ->whereIn('managed_id', $managedIds)
            ->get()
            ->keyBy('managed_id');

        foreach ($managedIds as $managedId) {
            $attachment = $attachments->get($managedId);

            if (! $attachment instanceof DavCalendarAttachment || $attachment->created_by_owner_id !== $currentOwnerId) {
                throw new Forbidden('Managed attachments can only be reused by their original creator.');
            }
        }
    }

    private function readCalendar(string $payload): VCalendar
    {
        try {
            $calendar = Reader::read($payload);
        } catch (Throwable) {
            throw new BadRequest('Calendar data could not be parsed.');
        }

        if (! $calendar instanceof VCalendar) {
            $calendar->destroy();

            throw new BadRequest('Calendar data could not be parsed.');
        }

        return $calendar;
    }

    /**
     * @return list<Component>
     */
    private function attachmentComponents(VCalendar $calendar): array
    {
        return array_values(array_filter(
            $calendar->getBaseComponents(),
            static fn (Component $component): bool => in_array($component->name, ['VEVENT', 'VTODO'], true),
        ));
    }

    private function componentHasManagedId(Component $component, string $managedId): bool
    {
        return $this->findAttachment($component, $managedId) !== null;
    }

    private function findAttachment(Component $component, string $managedId): ?Property
    {
        foreach ($component->select('ATTACH') as $property) {
            if ($property instanceof Property && $this->managedIdParameter($property) === $managedId) {
                return $property;
            }
        }

        return null;
    }

    private function componentHasAddress(Component $component, string $propertyName, string $email): bool
    {
        foreach ($component->select($propertyName) as $property) {
            if ($property instanceof Property && strtolower($property->getValue()) === 'mailto:'.strtolower($email)) {
                return true;
            }
        }

        return false;
    }

    private function managedIdParameter(Property $property): ?string
    {
        $parameter = $property['MANAGED-ID'];

        if (! $parameter instanceof Parameter) {
            return null;
        }

        $managedId = $parameter->getValue();

        return $managedId !== '' ? $managedId : null;
    }

    private function currentOwner(): ?Model
    {
        $ownerId = $this->currentOwnerId();

        if ($ownerId === null) {
            return null;
        }

        $ownerModel = Dav::ownerModel();

        return $ownerModel::query()->find($ownerId);
    }

    private function currentOwnerId(): ?int
    {
        $principal = $this->acl()->getCurrentUserPrincipal();

        return is_string($principal) ? $this->userIdFromPrincipalUri($principal) : null;
    }

    private function acl(): AclPlugin
    {
        $plugin = $this->server->getPlugin('acl');

        if (! $plugin instanceof AclPlugin) {
            throw new Forbidden('ACL support is required for managed attachments.');
        }

        return $plugin;
    }

    private function quoteHeaderValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
