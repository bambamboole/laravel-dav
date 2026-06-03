<?php

namespace Bambamboole\LaravelDav\Tests;

use Bambamboole\LaravelDav\LaravelDavServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelDavServiceProvider::class];
    }
}
