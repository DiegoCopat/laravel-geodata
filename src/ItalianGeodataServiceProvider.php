<?php

namespace DiegoCopat\ItalianGeodata;

use DiegoCopat\ItalianGeodata\Commands\SeedCommand;
use DiegoCopat\ItalianGeodata\Commands\StatsCommand;
use DiegoCopat\ItalianGeodata\Commands\UpdateCommand;
use DiegoCopat\ItalianGeodata\Services\FiscalCodeService;
use DiegoCopat\ItalianGeodata\Services\GeoDataService;
use Illuminate\Support\ServiceProvider;

class ItalianGeodataServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/italian-geodata.php', 'italian-geodata');

        $this->app->singleton(GeoDataService::class);
        $this->app->singleton(FiscalCodeService::class);
    }

    public function boot(): void
    {
        // Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publishable config
        $this->publishes([
            __DIR__ . '/../config/italian-geodata.php' => config_path('italian-geodata.php'),
        ], 'italian-geodata-config');

        // Publishable migrations (for customization)
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'italian-geodata-migrations');

        // Publishable data files
        $this->publishes([
            __DIR__ . '/../database/data' => database_path('data/italian-geodata'),
        ], 'italian-geodata-data');

        // Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SeedCommand::class,
                UpdateCommand::class,
                StatsCommand::class,
            ]);
        }
    }
}
