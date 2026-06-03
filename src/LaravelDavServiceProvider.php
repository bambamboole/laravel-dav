<?php

namespace Bambamboole\LaravelDav;

use Illuminate\Support\ServiceProvider;

class LaravelDavServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dav.php', 'dav');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->publishes([__DIR__.'/../config/dav.php' => config_path('dav.php')], 'dav-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'dav-migrations');
    }
}
