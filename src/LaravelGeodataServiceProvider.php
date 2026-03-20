<?php

namespace DiegoCopat\LaravelGeodata;

use DiegoCopat\LaravelGeodata\Commands\SeedCommand;
use DiegoCopat\LaravelGeodata\Commands\StatsCommand;
use DiegoCopat\LaravelGeodata\Commands\UpdateCommand;
use DiegoCopat\LaravelGeodata\Services\FiscalCodeService;
use DiegoCopat\LaravelGeodata\Services\GeoDataService;
use Illuminate\Support\ServiceProvider;

class LaravelGeodataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/geodata.php', 'geodata');

        $this->app->singleton(GeoDataService::class);
        $this->app->singleton(FiscalCodeService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/geodata.php' => config_path('geodata.php'),
        ], 'geodata-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'geodata-migrations');

        $this->publishes([
            __DIR__ . '/../database/data' => database_path('data/geodata'),
        ], 'geodata-data');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedCommand::class,
                UpdateCommand::class,
                StatsCommand::class,
            ]);
        }
    }
}
