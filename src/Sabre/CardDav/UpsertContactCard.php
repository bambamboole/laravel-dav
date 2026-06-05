<?php

namespace Bambamboole\LaravelDav\Sabre\CardDav;

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Parsing\VCardParser;
use Bambamboole\LaravelDav\Support\ContactCardProjection;

class UpsertContactCard
{
    public function __construct(
        private VCardParser $parser,
        private ContactCardProjection $projection,
    ) {}

    public function handle(DavAddressBook $addressBook, string $uri, string $payload): DavCard
    {
        $parsed = $this->parser->parse($payload, $uri);

        return $addressBook->cards()->updateOrCreate(
            ['uri' => $uri],
            [
                ...$this->projection->attributesFromData($parsed),
                'card_data' => $payload,
                'last_modified_at' => now(),
            ],
        );
    }
}
