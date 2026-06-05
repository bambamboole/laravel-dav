<?php

namespace Bambamboole\LaravelDav\Tests;

use Bambamboole\LaravelDav\LaravelDavServiceProvider;
use Bambamboole\LaravelDav\Tests\Stubs\OwnerUser;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\default_migration_path;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $server
     */
    public function callDav(string $method, string $path, ?string $authHeader = null, string $body = '', array $server = [], ?string $contentType = null): TestResponse
    {
        if ($authHeader !== null) {
            $server['HTTP_AUTHORIZATION'] = $authHeader;
        }

        if ($contentType !== null || $body !== '') {
            $server['CONTENT_TYPE'] = $contentType ?? 'application/xml';
        }

        return $this->call($method, $path, [], [], [], $server, $body);
    }

    protected function getPackageProviders($app): array
    {
        return [LaravelDavServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        Date::use(CarbonImmutable::class);

        $app['config']->set('dav.owner_model', OwnerUser::class);
        $app['config']->set('dav.owner_table', 'users');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(default_migration_path());
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
