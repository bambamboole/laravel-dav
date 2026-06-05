<?php

namespace Bambamboole\LaravelDav\Sabre\CardDav;

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCard;
use Bambamboole\LaravelDav\Parsing\VCardParser;

class UpsertContactCard
{
    public function __construct(
        private VCardParser $parser,
    ) {}

    public function handle(DavAddressBook $addressBook, string $uri, string $payload): DavCard
    {
        $parsed = $this->parser->parse($payload, $uri);
        $card = $addressBook->cards()->firstOrNew(['uri' => $uri]);

        $card->updateFromData($parsed, $payload);

        return $card->refresh();
    }
}
