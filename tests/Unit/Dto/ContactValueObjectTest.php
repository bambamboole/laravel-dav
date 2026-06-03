<?php

use Bambamboole\LaravelDav\Dto\Contact\ContactEmailAddress;
use Bambamboole\LaravelDav\Dto\Contact\ContactPostalAddress;

it('round-trips a ContactEmailAddress value object', function () {
    $data = [
        'label' => 'Work',
        'value' => 'test@example.com',
        'types' => ['internet', 'work'],
        'is_preferred' => true,
        'group' => 'item1',
    ];

    $email = new ContactEmailAddress($data);

    expect($email->label)->toBe('Work')
        ->and($email->value)->toBe('test@example.com')
        ->and($email->types)->toBe(['internet', 'work'])
        ->and($email->isPreferred)->toBeTrue()
        ->and($email->group)->toBe('item1')
        ->and($email->toArray())->toBe($data);
});

it('round-trips a ContactPostalAddress value object using NormalizesContactData trait', function () {
    $data = [
        'label' => 'Home',
        'po_box' => null,
        'extended' => null,
        'street' => '123 Main St',
        'city' => 'Springfield',
        'region' => 'IL',
        'postal_code' => '62701',
        'country' => 'United States',
        'country_code' => 'us',
        'types' => ['home'],
        'is_preferred' => false,
        'group' => null,
    ];

    $address = new ContactPostalAddress($data);

    expect($address->street)->toBe('123 Main St')
        ->and($address->city)->toBe('Springfield')
        ->and($address->postalCode)->toBe('62701')
        ->and($address->countryCode)->toBe('us')
        ->and($address->types)->toBe(['home'])
        ->and($address->isPreferred)->toBeFalse()
        ->and($address->toArray())->toBe($data);
});
