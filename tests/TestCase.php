<?php

namespace DiegoCopat\ItalianGeodata\Tests;

use DiegoCopat\ItalianGeodata\ItalianGeodataServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            ItalianGeodataServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'GeoData' => \DiegoCopat\ItalianGeodata\Facades\GeoData::class,
            'FiscalCode' => \DiegoCopat\ItalianGeodata\Facades\FiscalCode::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
