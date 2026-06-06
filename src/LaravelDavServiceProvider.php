<?php

namespace Bambamboole\LaravelDav;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LaravelDavServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dav.php', 'dav');

        $this->app->singleton(LaravelDav::class);
        $this->app->singletonIf(Server\ServerFactory::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if (! $this->app->routesAreCached()) {
            Route::middleware(config('dav.route.middleware', []))
                ->group(__DIR__.'/../routes/dav.php');
        }

        $this->publishes([__DIR__.'/../config/dav.php' => config_path('dav.php')], 'dav-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'dav-migrations');
    }
}
