<?php

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;

it('[sections 5.1 and 6.3.2] puts fetches and deletes a contact card through CardDAV', function (): void {
    $actor = davActor();
    $owner = $actor['owner'];

    DavAddressBook::factory()->create(['user_id' => $owner->getKey(), 'uri' => 'personal']);

    $payload = vcard(<<<'VCF'
        BEGIN:VCARD
        VERSION:3.0
        PRODID:-//Life OS//Tests//EN
        UID:contact-1
        FN:Ada Lovelace
        N:Lovelace;Ada;;;
        EMAIL;TYPE=work:ada@example.com
        END:VCARD
        VCF);

    $path = '/dav/addressbooks/'.$owner->getKey().'/personal/contact-1.vcf';

    davPut($this, $path, $actor['header'], $payload, 'text/vcard')->assertSuccessful();

    expect(DavCard::query()->where('uri', 'contact-1.vcf')->first())
        ->not->toBeNull()
        ->card_data->toBe($payload);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path)
        ->assertSuccessful()
        ->assertContent($payload);

    $this->withHeaders(['Authorization' => $actor['header']])
        ->delete($path)
        ->assertSuccessful();

    $this->withHeaders(['Authorization' => $actor['header']])
        ->get($path)
        ->assertNotFound();
});
