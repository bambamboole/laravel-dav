<?php

namespace Bambamboole\LaravelDav\Models;

use Bambamboole\LaravelDav\Database\Factories\DavCardFactory;
use Bambamboole\LaravelDav\Dto\Contact\ContactDate;
use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactInstantMessage;
use Bambamboole\LaravelDav\Dto\Contact\ContactPhoneNumber;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPronoun;
use Bambamboole\LaravelDav\Dto\Contact\ContactRelation;
use Bambamboole\LaravelDav\Dto\Contact\ContactSocialProfile;
use Bambamboole\LaravelDav\Dto\Contact\ContactUrl;
use Bambamboole\LaravelDav\Dto\Contact\ContactVCardExtension;
use Bambamboole\LaravelDav\Dto\ContactData;
use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Parsing\VCardSerializer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $dav_address_book_id
 * @property string $uri
 * @property string|null $uid
 * @property string|null $full_name
 * @property string|null $given_name
 * @property string|null $family_name
 * @property string|null $organization
 * @property array<array-key, mixed> $emails
 * @property array<array-key, mixed> $phones
 * @property string $etag
 * @property int $size
 * @property CarbonImmutable $last_modified_at
 * @property string $card_data
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property string $contact_type
 * @property string|null $name_prefix
 * @property string|null $middle_name
 * @property string|null $previous_family_name
 * @property string|null $name_suffix
 * @property string|null $nickname
 * @property string|null $phonetic_given_name
 * @property string|null $phonetic_middle_name
 * @property string|null $phonetic_family_name
 * @property string|null $job_title
 * @property string|null $department
 * @property string|null $phonetic_organization
 * @property string|null $note
 * @property ContactDate|null $birthday
 * @property Collection<int, ContactPronoun> $pronouns
 * @property Collection<int, ContactPhoneNumber> $phone_numbers
 * @property Collection<int, ContactEmailAddress> $email_addresses
 * @property Collection<int, ContactPostalAddress> $addresses
 * @property Collection<int, ContactUrl> $urls
 * @property Collection<int, ContactInstantMessage> $instant_messages
 * @property Collection<int, ContactSocialProfile> $social_profiles
 * @property Collection<int, ContactDate> $dates
 * @property Collection<int, ContactRelation> $relations
 * @property Collection<int, ContactVCardExtension> $vcard_extensions
 * @property-read DavAddressBook $addressBook
 */
class DavCard extends Model
{
    /** @use HasFactory<DavCardFactory> */
    use HasFactory;

    protected $table = 'dav_cards';

    protected $fillable = [
        'dav_address_book_id',
        'uri',
        'uid',
        'full_name',
        'given_name',
        'family_name',
        'organization',
        'contact_type',
        'name_prefix',
        'middle_name',
        'previous_family_name',
        'name_suffix',
        'nickname',
        'phonetic_given_name',
        'phonetic_middle_name',
        'phonetic_family_name',
        'job_title',
        'department',
        'phonetic_organization',
        'note',
        'birthday',
        'pronouns',
        'emails',
        'phones',
        'phone_numbers',
        'email_addresses',
        'addresses',
        'urls',
        'instant_messages',
        'social_profiles',
        'dates',
        'relations',
        'vcard_extensions',
        'etag',
        'size',
        'last_modified_at',
        'card_data',
    ];

    protected $attributes = [
        'contact_type' => 'person',
        'emails' => '[]',
        'phones' => '[]',
        'phone_numbers' => '[]',
        'email_addresses' => '[]',
        'addresses' => '[]',
        'urls' => '[]',
        'instant_messages' => '[]',
        'social_profiles' => '[]',
        'dates' => '[]',
        'relations' => '[]',
        'vcard_extensions' => '[]',
        'pronouns' => '[]',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'emails' => 'array',
            'phones' => 'array',
            'birthday' => ContactDate::class,
            'pronouns' => AsCollection::of(ContactPronoun::class),
            'phone_numbers' => AsCollection::of(ContactPhoneNumber::class),
            'email_addresses' => AsCollection::of(ContactEmailAddress::class),
            'addresses' => AsCollection::of(ContactPostalAddress::class),
            'urls' => AsCollection::of(ContactUrl::class),
            'instant_messages' => AsCollection::of(ContactInstantMessage::class),
            'social_profiles' => AsCollection::of(ContactSocialProfile::class),
            'dates' => AsCollection::of(ContactDate::class),
            'relations' => AsCollection::of(ContactRelation::class),
            'vcard_extensions' => AsCollection::of(ContactVCardExtension::class),
            'size' => 'integer',
            'last_modified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DavAddressBook, $this>
     */
    public function addressBook(): BelongsTo
    {
        return $this->belongsTo(Dav::modelFor('address_book', DavAddressBook::class), 'dav_address_book_id');
    }

    public function toData(): ContactData
    {
        return new ContactData(
            uri: (string) $this->uri,
            raw: (string) ($this->card_data ?? ''),
            etag: (string) ($this->etag ?? ''),
            size: (int) ($this->size ?? 0),
            uid: $this->uid,
            formattedName: $this->full_name,
            givenName: $this->given_name,
            familyName: $this->family_name,
            organization: $this->organization,
            contactType: (string) ($this->contact_type ?? 'person'),
            birthday: $this->birthday,
            emails: $this->email_addresses->all(),
            phones: $this->phone_numbers->all(),
            addresses: $this->addresses->all(),
            urls: $this->urls->all(),
            instantMessages: $this->instant_messages->all(),
            socialProfiles: $this->social_profiles->all(),
            dates: $this->dates->all(),
            relations: $this->getAttribute('relations')->all(),
            extensions: $this->vcard_extensions->all(),
            pronouns: $this->pronouns->all(),
            simpleEmails: (array) $this->emails,
            simplePhones: (array) $this->phones,
            namePrefix: $this->name_prefix,
            middleName: $this->middle_name,
            phoneticGivenName: $this->phonetic_given_name,
            phoneticMiddleName: $this->phonetic_middle_name,
            phoneticFamilyName: $this->phonetic_family_name,
            phoneticOrganization: $this->phonetic_organization,
            previousFamilyName: $this->previous_family_name,
            nameSuffix: $this->name_suffix,
            nickname: $this->nickname,
            jobTitle: $this->job_title,
            department: $this->department,
            note: $this->note,
        );
    }

    /**
     * Keep the payload, etag, and size consistent on every save. The vCard
     * payload is derived from the structured attributes only when none was
     * supplied (a DAV client's raw payload is authoritative); the etag and size
     * are always a pure function of that payload.
     */
    protected static function booted(): void
    {
        static::saving(function (self $card): void {
            if (blank($card->card_data)) {
                $card->card_data = app(VCardSerializer::class)->serialize($card->toData());
            }

            $card->etag = sha1($card->card_data);
            $card->size = strlen($card->card_data);
        });
    }

    protected static function newFactory(): DavCardFactory
    {
        return DavCardFactory::new();
    }
}
