<?php

namespace Bambamboole\LaravelDav\Tests\Integration\Support;

use Bambamboole\LaravelDav\Models\DavAddressBook;
use Bambamboole\LaravelDav\Models\DavCalendar;
use Bambamboole\LaravelDav\Models\DavCredential;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds two deterministic CalDAV/CardDAV accounts for the external
 * caldav-server-tester to authenticate against and exercise. The second
 * account lets the tester run its multi-user scheduling checks (RFC 6638).
 *
 * Runs inside the spawned `testbench db:seed` process, so it must rely only on
 * the package models and the OwnerUser stub (both available via the dev
 * autoloader). Credentials and collection identities come from
 * {@see CaldavTesterFixture} so the orchestrating test stays in sync.
 */
class CaldavTesterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAccount(
            CaldavTesterFixture::OWNER_NAME,
            CaldavTesterFixture::OWNER_EMAIL,
            CaldavTesterFixture::USERNAME,
            withAddressBook: true,
        );

        $this->seedAccount(
            CaldavTesterFixture::SECOND_OWNER_NAME,
            CaldavTesterFixture::SECOND_OWNER_EMAIL,
            CaldavTesterFixture::SECOND_USERNAME,
        );
    }

    private function seedAccount(string $name, string $email, string $username, bool $withAddressBook = false): void
    {
        $owner = OwnerUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(CaldavTesterFixture::SECRET),
        ]);

        DavCredential::query()->create([
            'user_id' => $owner->getKey(),
            'name' => 'caldav-server-tester',
            'username' => $username,
            'secret_hash' => Hash::make(CaldavTesterFixture::SECRET),
        ]);

        DavCalendar::query()->create([
            'user_id' => $owner->getKey(),
            'uri' => CaldavTesterFixture::CALENDAR_URI,
            'display_name' => CaldavTesterFixture::CALENDAR_DISPLAY_NAME,
            'components' => ['VEVENT', 'VTODO', 'VJOURNAL'],
        ]);

        if ($withAddressBook) {
            DavAddressBook::query()->create([
                'user_id' => $owner->getKey(),
                'uri' => CaldavTesterFixture::ADDRESS_BOOK_URI,
                'display_name' => CaldavTesterFixture::ADDRESS_BOOK_DISPLAY_NAME,
            ]);
        }
    }
}
