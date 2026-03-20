<?php

namespace DiegoCopat\ItalianGeodata\Commands;

use DiegoCopat\ItalianGeodata\Models\City;
use DiegoCopat\ItalianGeodata\Models\Country;
use DiegoCopat\ItalianGeodata\Models\Province;
use DiegoCopat\ItalianGeodata\Models\Region;
use Illuminate\Console\Command;

class StatsCommand extends Command
{
    protected $signature = 'geodata:stats';

    protected $description = 'Show statistics about loaded geographic data';

    public function handle(): int
    {
        $this->info('Italian Geodata Statistics');
        $this->info('=========================');
        $this->newLine();

        // Countries
        $totalCountries = Country::count();
        $activeCountries = Country::active()->count();
        $historicalCountries = $totalCountries - $activeCountries;

        $this->components->twoColumnDetail('Countries (total)', (string) $totalCountries);
        $this->components->twoColumnDetail('  Active', (string) $activeCountries);
        $this->components->twoColumnDetail('  Historical', (string) $historicalCountries);

        $this->newLine();

        // Regions
        $totalRegions = Region::count();
        $this->components->twoColumnDetail('Regions', (string) $totalRegions);

        $this->newLine();

        // Provinces
        $totalProvinces = Province::count();
        $this->components->twoColumnDetail('Provinces', (string) $totalProvinces);

        // Province types breakdown
        $provinceTypes = Province::query()
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        foreach ($provinceTypes as $type => $count) {
            $label = $type ?: 'N/A';
            $this->components->twoColumnDetail("  {$label}", (string) $count);
        }

        $this->newLine();

        // Cities
        $totalCities = City::count();
        $activeCities = City::active()->count();
        $capitals = City::capitals()->count();
        $withCap = City::whereNotNull('cap')->count();
        $withCoords = City::whereNotNull('lat')->whereNotNull('lon')->count();

        $this->components->twoColumnDetail('Cities (total)', (string) $totalCities);
        $this->components->twoColumnDetail('  Active', (string) $activeCities);
        $this->components->twoColumnDetail('  Capitals', (string) $capitals);
        $this->components->twoColumnDetail('  With CAP', (string) $withCap);
        $this->components->twoColumnDetail('  With coordinates', (string) $withCoords);

        $this->newLine();

        // Top provinces by city count
        $this->info('Top 10 provinces by number of cities:');
        $topProvinces = Province::query()
            ->withCount('cities')
            ->orderByDesc('cities_count')
            ->limit(10)
            ->get();

        foreach ($topProvinces as $province) {
            $this->components->twoColumnDetail(
                "  {$province->name} ({$province->code})",
                (string) $province->cities_count
            );
        }

        $this->newLine();

        // Data source info
        $dataPath = dirname(__DIR__, 2) . '/database/data';
        if (file_exists($dataPath . '/gi_comuni.json')) {
            $mtime = filemtime($dataPath . '/gi_comuni.json');
            $this->components->twoColumnDetail(
                'Data files last updated',
                date('Y-m-d H:i', $mtime)
            );
        }

        return self::SUCCESS;
    }
}
