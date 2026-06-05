<?php

namespace Bambamboole\LaravelDav\Database\Factories;

use Bambamboole\LaravelDav\Facades\Dav;
use Bambamboole\LaravelDav\Models\DavCalendarProxyMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DavCalendarProxyMembership>
 */
class DavCalendarProxyMembershipFactory extends Factory
{
    protected $model = DavCalendarProxyMembership::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'owner_id' => (Dav::ownerModel())::factory(),
            'delegate_owner_id' => (Dav::ownerModel())::factory(),
            'access' => DavCalendarProxyMembership::AccessRead,
        ];
    }
}
